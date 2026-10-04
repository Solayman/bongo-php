<?php
namespace System\Data;

use System\Storage\DownloadResponse;

/**
 * Universal Printable Document & Vector PDF Engine.
 *
 * Solves the classic problem where traditional PHP PDF engines break complex
 * Indic/Bengali scripts, conjuncts (যুক্তাক্ষর), and vowel marks.
 *
 * It generates pixel-perfect, print-optimized HTML5 documents with responsive
 * @media print CSS, native font rendering, and an optional headless browser
 * PDF compilation bridge (Google Chrome, Microsoft Edge, Chromium, wkhtmltopdf).
 */
class Printable
{
    protected string $title = 'INVOICE';
    protected ?string $badge = null;
    protected string $badgeColor = '#10b981'; // Green
    protected array $company = [];
    protected array $customer = [];
    protected array $shipping = [];
    protected array $meta = [];
    protected array $tableHeaders = [];
    protected array $tableWidths = [];
    protected array $tableAligns = [];
    protected array $tableRows = [];
    protected array $totals = [];
    protected array $notes = [];
    protected bool $autoPrint = false;
    protected string $fontFamily = "system-ui, -apple-system, 'Segoe UI', 'Noto Sans Bengali', 'Kalpurush', 'SolaimanLipi', Roboto, Arial, sans-serif";

    public function __construct(string $title = 'INVOICE')
    {
        $this->title = $title;
    }

    public static function make(string $title = 'INVOICE'): self
    {
        return new self($title);
    }

    public static function invoice(string $invoiceNumber): self
    {
        return (new self("INVOICE #$invoiceNumber"))->badge('UNPAID', '#ef4444');
    }

    public function badge(string $text, string $color = '#10b981'): self
    {
        $this->badge = $text;
        $this->badgeColor = $color;
        return $this;
    }

    public function company(array $details): self
    {
        $this->company = $details;
        return $this;
    }

    public function customer(array $details): self
    {
        $this->customer = $details;
        return $this;
    }

    public function shipping(array $details): self
    {
        $this->shipping = $details;
        return $this;
    }

    public function meta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    public function table(array $headers, array $rows, array $widths = [], array $aligns = []): self
    {
        $this->tableHeaders = $headers;
        $this->tableRows = $rows;
        $this->tableWidths = $widths;
        $this->tableAligns = $aligns;
        return $this;
    }

    public function totals(array $totals): self
    {
        $this->totals = $totals;
        return $this;
    }

    public function notes(array|string $notes): self
    {
        $this->notes = is_array($notes) ? $notes : [$notes];
        return $this;
    }

    public function autoPrint(bool $enable = true): self
    {
        $this->autoPrint = $enable;
        return $this;
    }

    /**
     * Render the complete standalone HTML document.
     */
    public function render(): string
    {
        $safeTitle = htmlspecialchars($this->title, ENT_QUOTES, 'UTF-8');
        $autoPrintJs = $this->autoPrint ? '<script>window.addEventListener("DOMContentLoaded", () => { setTimeout(() => window.print(), 300); });</script>' : '';

        // Company block
        $companyHtml = '';
        foreach ($this->company as $key => $val) {
            if ($key === 'name') {
                $companyHtml .= '<div style="font-size: 20px; font-weight: 700; color: #111827; margin-bottom: 4px;">' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</div>';
            } else {
                $companyHtml .= '<div style="color: #4b5563; font-size: 13px; line-height: 1.4;">' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</div>';
            }
        }

        // Customer block
        $customerHtml = '';
        if (!empty($this->customer)) {
            $customerHtml .= '<div style="flex: 1; min-width: 200px;"><div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; margin-bottom: 6px;">BILL TO / গ্রাহক</div>';
            foreach ($this->customer as $key => $val) {
                if ($key === 'name') {
                    $customerHtml .= '<div style="font-weight: 600; color: #111827; font-size: 14px;">' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</div>';
                } else {
                    $customerHtml .= '<div style="color: #4b5563; font-size: 13px;">' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</div>';
                }
            }
            $customerHtml .= '</div>';
        }

        // Shipping block
        $shippingHtml = '';
        if (!empty($this->shipping)) {
            $shippingHtml .= '<div style="flex: 1; min-width: 200px;"><div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; margin-bottom: 6px;">SHIP TO / গন্তব্য</div>';
            foreach ($this->shipping as $key => $val) {
                if ($key === 'name') {
                    $shippingHtml .= '<div style="font-weight: 600; color: #111827; font-size: 14px;">' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</div>';
                } else {
                    $shippingHtml .= '<div style="color: #4b5563; font-size: 13px;">' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</div>';
                }
            }
            $shippingHtml .= '</div>';
        }

        // Meta block
        $metaHtml = '';
        if (!empty($this->meta)) {
            $metaHtml .= '<div style="flex: 1; min-width: 180px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px;">';
            foreach ($this->meta as $label => $value) {
                $metaHtml .= '<div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">'
                    . '<span style="color: #6b7280; font-weight: 500;">' . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . ':</span>'
                    . '<span style="color: #111827; font-weight: 600;">' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '</div>';
            }
            $metaHtml .= '</div>';
        }

        // Table
        $tableHtml = '';
        if (!empty($this->tableHeaders)) {
            $tableHtml .= '<table style="width: 100%; border-collapse: collapse; margin-top: 24px; font-size: 13px;"><thead><tr style="background: #f3f4f6; border-bottom: 2px solid #e5e7eb;">';
            foreach ($this->tableHeaders as $idx => $hdr) {
                $width = !empty($this->tableWidths[$idx]) ? ' width="' . $this->tableWidths[$idx] . '%"' : '';
                $align = !empty($this->tableAligns[$idx]) ? $this->tableAligns[$idx] : 'left';
                $tableHtml .= '<th style="padding: 10px 12px; text-align: ' . $align . '; color: #374151; font-weight: 600;"' . $width . '>' . htmlspecialchars((string) $hdr, ENT_QUOTES, 'UTF-8') . '</th>';
            }
            $tableHtml .= '</tr></thead><tbody>';

            foreach ($this->tableRows as $rIdx => $row) {
                $rowBg = ($rIdx % 2 === 1) ? ' style="background: #fafafa; border-bottom: 1px solid #f3f4f6;"' : ' style="border-bottom: 1px solid #f3f4f6;"';
                $tableHtml .= "<tr$rowBg>";
                foreach (array_values($row) as $cIdx => $val) {
                    $align = !empty($this->tableAligns[$cIdx]) ? $this->tableAligns[$cIdx] : 'left';
                    $tableHtml .= '<td style="padding: 10px 12px; text-align: ' . $align . '; color: #1f2937;">' . htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') . '</td>';
                }
                $tableHtml .= '</tr>';
            }
            $tableHtml .= '</tbody></table>';
        }

        // Totals summary
        $totalsHtml = '';
        if (!empty($this->totals)) {
            $totalsHtml .= '<div style="margin-top: 20px; display: flex; justify-content: flex-end;"><div style="width: 260px;">';
            $isLast = false;
            $count = count($this->totals);
            $i = 0;
            foreach ($this->totals as $lbl => $amt) {
                $i++;
                $isLast = ($i === $count);
                $rowStyle = $isLast
                    ? 'border-top: 2px solid #111827; padding-top: 8px; margin-top: 6px; font-size: 15px; font-weight: 700; color: #111827;'
                    : 'padding: 4px 0; font-size: 13px; color: #4b5563;';
                $totalsHtml .= '<div style="display: flex; justify-content: space-between; ' . $rowStyle . '">'
                    . '<span>' . htmlspecialchars((string) $lbl, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span>' . htmlspecialchars((string) $amt, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '</div>';
            }
            $totalsHtml .= '</div></div>';
        }

        // Notes block
        $notesHtml = '';
        if (!empty($this->notes)) {
            $notesHtml .= '<div style="margin-top: 36px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280;">'
                . '<div style="font-weight: 600; text-transform: uppercase; margin-bottom: 4px; color: #374151;">NOTES & TERMS / শর্তাবলী:</div>';
            foreach ($this->notes as $note) {
                $notesHtml .= '<p style="margin: 2px 0;">' . htmlspecialchars((string) $note, ENT_QUOTES, 'UTF-8') . '</p>';
            }
            $notesHtml .= '</div>';
        }

        // Badge HTML
        $badgeHtml = $this->badge
            ? '<span style="display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700; letter-spacing: 0.05em; background: ' . $this->badgeColor . '20; color: ' . $this->badgeColor . '; border: 1px solid ' . $this->badgeColor . '50;">' . htmlspecialchars($this->badge, ENT_QUOTES, 'UTF-8') . '</span>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$safeTitle</title>
    <style>
        @media print {
            body { background: #fff !important; padding: 0 !important; margin: 0 !important; }
            .no-print { display: none !important; }
            .printable-card { border: none !important; box-shadow: none !important; padding: 0 !important; max-width: 100% !important; }
            @page { size: A4; margin: 15mm; }
        }
        body {
            font-family: {$this->fontFamily};
            background: #f3f4f6;
            color: #111827;
            margin: 0;
            padding: 32px 16px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .no-print-bar {
            max-width: 820px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-print {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .btn-print:hover { background: #1d4ed8; }
        .printable-card {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
    </style>
    $autoPrintJs
</head>
<body>
    <div class="no-print-bar no-print">
        <a href="javascript:history.back()" style="color: #4b5563; font-size: 13px; text-decoration: none;">&larr; Back</a>
        <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
    </div>

    <div class="printable-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px;">
            <div>$companyHtml</div>
            <div style="text-align: right;">
                <div style="font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -0.02em; margin-bottom: 6px;">$safeTitle</div>
                $badgeHtml
            </div>
        </div>

        <div style="display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 24px;">
            $customerHtml
            $shippingHtml
            $metaHtml
        </div>

        $tableHtml
        $totalsHtml
        $notesHtml
    </div>
</body>
</html>
HTML;
    }

    /**
     * Compile this printable document directly into a binary PDF using a local headless browser.
     *
     * Supports Google Chrome, Microsoft Edge, Chromium, or wkhtmltopdf.
     * Guaranteed 100% pixel-perfect vector font rendering for complex scripts (Bangla, Arabic).
     */
    public function toPdf(string $outputPath): bool
    {
        $bin = self::detectHeadlessBinary();
        if (!$bin) {
            return false;
        }

        $tmpHtml = tempnam(sys_get_temp_dir(), 'bongo_pdf_') . '.html';
        file_put_contents($tmpHtml, $this->render());

        $cmd = '';
        if (str_contains(strtolower($bin), 'wkhtmltopdf')) {
            $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($tmpHtml) . ' ' . escapeshellarg($outputPath);
        } else {
            // Chrome / Edge / Chromium headless syntax
            $cmd = escapeshellarg($bin)
                . ' --headless=new --disable-gpu --no-sandbox --no-pdf-header-footer'
                . ' --print-to-pdf=' . escapeshellarg($outputPath) . ' ' . escapeshellarg($tmpHtml);
        }

        exec($cmd, $output, $code);
        @unlink($tmpHtml);

        return is_file($outputPath) && filesize($outputPath) > 0;
    }

    /**
     * Download as PDF if a headless browser is available, or download as printable HTML.
     */
    public function download(string $filename = 'document.pdf'): DownloadResponse
    {
        $tmpPdf = tempnam(sys_get_temp_dir(), 'bongo_doc_') . '.pdf';

        if ($this->toPdf($tmpPdf)) {
            return new DownloadResponse($tmpPdf, $filename, 'application/pdf');
        }

        // Fallback to printable HTML if no headless browser installed on server
        @unlink($tmpPdf);
        $tmpHtml = tempnam(sys_get_temp_dir(), 'bongo_doc_') . '.html';
        file_put_contents($tmpHtml, $this->render());
        $htmlName = preg_replace('/\.pdf$/i', '.html', $filename);

        return new DownloadResponse($tmpHtml, $htmlName, 'text/html; charset=UTF-8');
    }

    /**
     * Detect an installed headless rendering binary across Windows and Linux.
     */
    public static function detectHeadlessBinary(): ?string
    {
        $candidates = [
            // Windows common locations
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            // Linux common locations
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/bin/wkhtmltopdf',
            '/usr/local/bin/wkhtmltopdf',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        // Check PATH
        $which = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        foreach (['chrome', 'google-chrome', 'chromium', 'msedge', 'wkhtmltopdf'] as $name) {
            $out = @shell_exec("$which $name 2>nul");
            if ($out && trim($out) !== '' && is_file(trim(strtok($out, "\r\n")))) {
                return trim(strtok($out, "\r\n"));
            }
        }

        return null;
    }
}
