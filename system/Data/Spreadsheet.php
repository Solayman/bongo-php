<?php
namespace System\Data;

use InvalidArgumentException;
use RuntimeException;
use System\Http\UploadedFile;
use System\Storage\DownloadResponse;
use System\Storage\Storage;
use ZipArchive;

/**
 * Unified Spreadsheet Engine (CSV & Excel .xlsx).
 *
 * Provides a single, frictionless API for importing and exporting both CSV
 * and genuine OpenXML Excel (.xlsx) files with zero external dependencies.
 *
 * Features:
 *  - Automatic format detection (.xlsx, .csv, .tsv) via extension or file magic bytes.
 *  - Full UTF-8 BOM (\xEF\xBB\xBF) encoding for CSVs (prevents broken Bangla/accents in Excel).
 *  - Native OpenXML .xlsx generation with bold headers using PHP's built-in ZipArchive.
 *  - Reads both inline strings and sharedStrings.xml tables in uploaded .xlsx files.
 *  - Streaming downloads via System\Storage\DownloadResponse.
 */
class Spreadsheet
{
    /**
     * Download data directly to the user's browser as .xlsx or .csv based on filename extension.
     *
     *   return Spreadsheet::download($orders, 'sales-2026.xlsx', ['id' => 'Order ID', 'total' => 'Total ($)']);
     *   return Spreadsheet::download($users, 'customers.csv');
     */
    public static function download(iterable $rows, string $filename, array $columns = []): DownloadResponse
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            $filename .= '.csv';
            $ext = 'csv';
        }
        $tmp = tempnam(sys_get_temp_dir(), 'bongo_sheet_');
        file_put_contents($tmp, self::content($rows, $ext, $columns));

        $mime = ($ext === 'xlsx')
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=UTF-8';

        // The temporary file is deleted once it has been sent
        return new DownloadResponse($tmp, $filename, [], $mime, deleteAfterSend: true);
    }

    /**
     * Store data directly to an isolated storage disk (default is 'public').
     */
    public static function store(iterable $rows, string $path, array $columns = [], ?string $disk = null): string
    {
        Storage::disk($disk)->put($path, self::content($rows, $path, $columns));
        return $path;
    }

    /**
     * Generate in-memory content (.xlsx binary or .csv text) based on extension or filename.
     */
    public static function content(iterable $rows, string $extensionOrPath, array $columns = []): string
    {
        $ext = strtolower(pathinfo($extensionOrPath, PATHINFO_EXTENSION) ?: $extensionOrPath);
        return ($ext === 'xlsx')
            ? self::toXlsx($rows, $columns)
            : self::toCsv($rows, $columns);
    }

    /**
     * Read any uploaded spreadsheet (.xlsx or .csv) into an associative array of rows.
     * Automatically detects format and uses the first non-empty row as header keys.
     *
     *   $data = Spreadsheet::read($request->file('import'));
     *   $data = Spreadsheet::read('/path/to/inventory.xlsx');
     */
    public static function read(mixed $file): array
    {
        $path = $file instanceof UploadedFile ? $file->path() : (string) $file;

        if (!is_file($path)) {
            throw new InvalidArgumentException("Spreadsheet file not found at: $path");
        }

        // Check magic bytes: PK\x03\x04 indicates a ZIP archive (OpenXML .xlsx)
        $fh = @fopen($path, 'rb');
        $magic = $fh ? fread($fh, 4) : '';
        if ($fh) fclose($fh);

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($magic === "PK\x03\x04" || $ext === 'xlsx') {
            return self::readXlsx($path);
        }

        return self::readCsv($path);
    }

    /**
     * Generate an in-memory CSV string with UTF-8 BOM.
     */
    public static function toCsv(iterable $rows, array $columns = []): string
    {
        $fh = fopen('php://temp', 'r+');
        // Write UTF-8 BOM so Excel opens Bangla and Unicode characters cleanly
        fwrite($fh, "\xEF\xBB\xBF");

        $normalized = self::normalizeRows($rows, $columns);
        $headersWritten = false;

        foreach ($normalized as $row) {
            if (!$headersWritten) {
                fputcsv($fh, array_map([self::class, 'csvSafe'], array_keys($row)));
                $headersWritten = true;
            }
            fputcsv($fh, array_map([self::class, 'csvSafe'], array_values($row)));
        }

        rewind($fh);
        $output = stream_get_contents($fh);
        fclose($fh);

        return $output;
    }

    /**
     * Excel runs a CSV cell that starts with = + - @ as a formula. A customer named
     * "=HYPERLINK(...)" must stay text, so such cells get a leading apostrophe.
     * Real numbers (-5, +3.2) are left alone.
     */
    public static function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && !is_numeric($value) && str_contains("=+-@\t\r", $value[0])) {
            return "'" . $value;
        }
        return $value;
    }

    /** Text for an XML cell: invalid UTF-8 replaced, control characters Excel rejects removed */
    protected static function xmlText(mixed $value): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value);
        return htmlspecialchars($text, ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Unpacked size limit per xlsx part, so a small "zip bomb" upload can't exhaust memory */
    protected const MAX_XLSX_PART = 50 * 1024 * 1024;

    protected static function zipPart(ZipArchive $zip, string $name): string|false
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            return false;
        }
        if ($stat['size'] > self::MAX_XLSX_PART) {
            throw new RuntimeException("The .xlsx part $name unpacks to {$stat['size']} bytes, over the " . self::MAX_XLSX_PART . ' byte limit.');
        }
        return $zip->getFromName($name);
    }

    /**
     * Generate an in-memory OpenXML .xlsx workbook.
     */
    public static function toXlsx(iterable $rows, array $columns = []): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP ZipArchive extension is required to export .xlsx files.');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'bongo_xlsx_');
        $zip = new ZipArchive();

        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create temporary zip archive at: $tmpFile");
        }

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
        $zip->addFromString('_rels/.rels', $rootRels);

        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 4. xl/styles.xml (Bold header style with gray fill)
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            . '</cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // 5. xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="Sheet1" sheetId="1" r:id="rId1"/>'
            . '</sheets>'
            . '</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // 6. xl/worksheets/sheet1.xml
        $normalized = self::normalizeRows($rows, $columns);
        $sheetData = '';
        $rNum = 1;
        $headersWritten = false;

        foreach ($normalized as $row) {
            if (!$headersWritten) {
                // Header row with style 1 (bold + shaded)
                $sheetData .= "<row r=\"$rNum\">";
                $cNum = 0;
                foreach (array_keys($row) as $header) {
                    $ref = self::colLetter($cNum) . $rNum;
                    $safe = self::xmlText($header);
                    $sheetData .= "<c r=\"$ref\" s=\"1\" t=\"inlineStr\"><is><t>$safe</t></is></c>";
                    $cNum++;
                }
                $sheetData .= '</row>';
                $headersWritten = true;
                $rNum++;
            }

            // Data row
            $sheetData .= "<row r=\"$rNum\">";
            $cNum = 0;
            foreach (array_values($row) as $val) {
                $ref = self::colLetter($cNum) . $rNum;
                if (is_int($val) || (is_float($val) && is_finite($val))) {
                    $sheetData .= "<c r=\"$ref\"><v>$val</v></c>";
                } elseif (is_numeric($val) && !str_starts_with((string) $val, '0') && strlen((string) $val) < 15) {
                    $sheetData .= "<c r=\"$ref\"><v>$val</v></c>";
                } else {
                    $safe = self::xmlText($val);
                    $sheetData .= "<c r=\"$ref\" t=\"inlineStr\"><is><t>$safe</t></is></c>";
                }
                $cNum++;
            }
            $sheetData .= '</row>';
            $rNum++;
        }

        $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $sheetData . '</sheetData>'
            . '</worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheet);

        $zip->close();
        $binary = file_get_contents($tmpFile);
        @unlink($tmpFile);

        return $binary;
    }

    /**
     * Read a CSV file into an array of associative rows.
     */
    public static function readCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        if (!$fh) {
            return [];
        }

        // Check and strip UTF-8 BOM
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }

        // Sniff delimiter from first line
        $firstLine = fgets($fh);
        rewind($fh);
        if ($bom === "\xEF\xBB\xBF") {
            fread($fh, 3);
        }

        $delimiter = ',';
        if ($firstLine !== false) {
            $commas = substr_count($firstLine, ',');
            $semis  = substr_count($firstLine, ';');
            $tabs   = substr_count($firstLine, "\t");
            if ($semis > $commas && $semis > $tabs) $delimiter = ';';
            elseif ($tabs > $commas && $tabs > $semis) $delimiter = "\t";
        }

        $headers = [];
        $rows = [];

        while (($data = fgetcsv($fh, 0, $delimiter)) !== false) {
            // Skip empty rows
            if (count($data) === 1 && $data[0] === null) continue;

            if (empty($headers)) {
                $headers = array_map('trim', $data);
                continue;
            }

            $rows[] = self::mapRow($headers, $data);
        }

        fclose($fh);
        return $rows;
    }

    /**
     * Read an OpenXML .xlsx file into an array of associative rows.
     */
    public static function readXlsx(string $path): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP ZipArchive extension is required to read .xlsx files.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Cannot open .xlsx file at: $path");
        }

        // 1. Read shared strings if present
        $sharedStrings = [];
        $sharedXml = self::zipPart($zip, 'xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $xml = @simplexml_load_string($sharedXml, 'SimpleXMLElement', LIBXML_NONET);
            if ($xml && isset($xml->si)) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string) ($r->t ?? '');
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Read sheet1.xml
        $sheetXml = self::zipPart($zip, 'xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $xml = @simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NONET);
        if (!$xml || !isset($xml->sheetData->row)) {
            return [];
        }

        $headers = [];
        $rows = [];

        foreach ($xml->sheetData->row as $r) {
            $cells = [];
            foreach ($r->c as $c) {
                $ref = (string) $c['r'];
                preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
                $colLetter = $m[1] ?? 'A';
                $colIndex = self::colIndex($colLetter);

                $t = (string) $c['t'];
                $val = '';

                if ($t === 's') {
                    $idx = (int) $c->v;
                    $val = $sharedStrings[$idx] ?? '';
                } elseif ($t === 'inlineStr') {
                    $val = (string) ($c->is->t ?? '');
                } elseif ($t === 'b') {
                    $val = ((string) $c->v === '1');
                } else {
                    $val = (string) ($c->v ?? '');
                }

                $cells[$colIndex] = $val;
            }

            if (empty($cells)) continue;

            // Fill empty intermediate columns
            $maxCol = max(array_keys($cells));
            $denseRow = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $denseRow[] = $cells[$i] ?? '';
            }

            if (empty($headers)) {
                $headers = array_map('trim', $denseRow);
                continue;
            }

            $rows[] = self::mapRow($headers, $denseRow);
        }

        return $rows;
    }

    /** Map positional values into an associative row keyed by headers */
    protected static function mapRow(array $headers, array $values): array
    {
        $row = [];
        foreach ($headers as $idx => $key) {
            $row[$key] = $values[$idx] ?? '';
        }
        return $row;
    }

    /**
     * Convert column letter (A, B, ..., Z, AA, AB) to 0-based index.
     */
    protected static function colIndex(string $col): int
    {
        $idx = 0;
        $len = strlen($col);
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($col[$i]) - 64);
        }
        return $idx - 1;
    }

    /**
     * Convert 0-based column index to letter (0 -> A, 25 -> Z, 26 -> AA).
     */
    protected static function colLetter(int $col): string
    {
        $letter = '';
        $col += 1;
        while ($col > 0) {
            $rem = ($col - 1) % 26;
            $letter = chr(65 + $rem) . $letter;
            $col = (int) (($col - $rem) / 26);
        }
        return $letter;
    }

    /**
     * Normalize input rows (arrays, models, objects) against optional column mappings.
     */
    protected static function normalizeRows(iterable $rows, array $columns): array
    {
        $result = [];

        foreach ($rows as $item) {
            $rowArray = is_object($item)
                ? (method_exists($item, 'toArray') ? $item->toArray() : (array) $item)
                : (array) $item;

            if (!empty($columns)) {
                $filtered = [];
                foreach ($columns as $sourceKey => $targetLabel) {
                    if (is_numeric($sourceKey)) {
                        $sourceKey = $targetLabel;
                    }
                    $filtered[$targetLabel] = $rowArray[$sourceKey] ?? '';
                }
                $result[] = $filtered;
            } else {
                $result[] = $rowArray;
            }
        }

        return $result;
    }
}
