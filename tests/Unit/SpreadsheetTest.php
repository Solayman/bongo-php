<?php

namespace Tests\Unit;

use System\Data\Spreadsheet;
use System\Storage\DownloadResponse;
use System\Testing\TestCase;

class SpreadsheetTest extends TestCase
{
    protected string $dir;

    public function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bongo_sheet_test_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    public function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function testCsvExportAndReadWithBanglaAndUtf8Bom(): void
    {
        $data = [
            ['name' => 'আম (Mango)', 'sku' => 'MNG-01', 'price' => 120.50, 'stock' => 50],
            ['name' => 'কাঁঠাল (Jackfruit)', 'sku' => 'JCK-02', 'price' => 250.00, 'stock' => 10],
        ];

        $columns = [
            'name'  => 'পণ্য (Product)',
            'sku'   => 'কোড (SKU)',
            'price' => 'মূল্য (Price)',
            'stock' => 'মজুদ (Stock)',
        ];

        $csvString = Spreadsheet::toCsv($data, $columns);

        // Verify UTF-8 BOM prefix
        $this->assertTrue(str_starts_with($csvString, "\xEF\xBB\xBF"));
        $this->assertContains('পণ্য (Product)', $csvString);
        $this->assertContains('আম (Mango)', $csvString);

        // Write to file and read back
        $filePath = $this->dir . '/products.csv';
        file_put_contents($filePath, $csvString);

        $imported = Spreadsheet::read($filePath);
        $this->assertCount(2, $imported);
        $this->assertSame('আম (Mango)', $imported[0]['পণ্য (Product)']);
        $this->assertSame('MNG-01', $imported[0]['কোড (SKU)']);
        $this->assertSame('120.5', $imported[0]['মূল্য (Price)']);
        $this->assertSame('কাঁঠাল (Jackfruit)', $imported[1]['পণ্য (Product)']);
    }

    public function testXlsxExportAndReadRoundTrip(): void
    {
        $data = [
            ['customer' => 'রহিম আহমেদ (Rahim)', 'order_id' => 1001, 'amount' => 1500.75],
            ['customer' => 'করিম মিয়া (Karim)', 'order_id' => 1002, 'amount' => 3200.00],
        ];

        $columns = [
            'customer' => 'Customer',
            'order_id' => 'Order ID',
            'amount'   => 'Total Amount',
        ];

        $xlsxBinary = Spreadsheet::toXlsx($data, $columns);
        $this->assertTrue(strlen($xlsxBinary) > 1000);
        $this->assertTrue(str_starts_with($xlsxBinary, "PK\x03\x04"));

        $filePath = $this->dir . '/orders.xlsx';
        file_put_contents($filePath, $xlsxBinary);

        // Read using unified Spreadsheet::read()
        $imported = Spreadsheet::read($filePath);
        $this->assertCount(2, $imported);
        $this->assertSame('রহিম আহমেদ (Rahim)', $imported[0]['Customer']);
        $this->assertSame('1001', $imported[0]['Order ID']);
        $this->assertSame('1500.75', $imported[0]['Total Amount']);
        $this->assertSame('করিম মিয়া (Karim)', $imported[1]['Customer']);
    }

    public function testSpreadsheetDownloadReturnsProperResponse(): void
    {
        $data = [
            ['id' => 1, 'title' => 'Report 1'],
        ];

        $csvResponse = Spreadsheet::download($data, 'export.csv');
        $this->assertTrue($csvResponse instanceof DownloadResponse);
        $this->assertSame('export.csv', $csvResponse->filename);
        $this->assertSame('text/csv; charset=UTF-8', $csvResponse->mimeType);

        $xlsxResponse = Spreadsheet::download($data, 'export.xlsx');
        $this->assertTrue($xlsxResponse instanceof DownloadResponse);
        $this->assertSame('export.xlsx', $xlsxResponse->filename);
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $xlsxResponse->mimeType);
    }
}
