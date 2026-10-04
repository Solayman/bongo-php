<?php

namespace Tests\Unit;

use System\Data\Printable;
use System\Testing\TestCase;

class PrintableTest extends TestCase
{
    public function testInvoiceRendersFlawlessHtmlWithBanglaTypography(): void
    {
        $doc = Printable::invoice('INV-2026-99')
            ->badge('পরিশোধিত (PAID)', '#10b981')
            ->company([
                'name'    => 'নবটেক লিমিটেড (Nobotech Ltd)',
                'address' => 'ঢাকা, বাংলাদেশ (Dhaka, Bangladesh)',
                'phone'   => '+880 1700-000000',
            ])
            ->customer([
                'name'    => 'মোঃ সোলায়মান আলী (Solayman Ali)',
                'address' => 'গুলশান-২, ঢাকা',
            ])
            ->meta([
                'তারিখ (Date)' => '2026-10-04',
                'Payment'      => 'bKash / কার্ড',
            ])
            ->table(
                headers: ['বিবরণ (Item)', 'পরিমাণ (Qty)', 'দর (Price)', 'মোট (Total)'],
                rows: [
                    ['ওয়েব হোস্টিং (Cloud VPS)', '১', '৳ ২,৫০০', '৳ ২,৫০০'],
                    ['এসএসএল সার্টিফিকেট (SSL)', '১', '৳ ১,০০০', '৳ ১,০০০'],
                ],
                widths: [50, 15, 15, 20],
                aligns: ['left', 'center', 'right', 'right']
            )
            ->totals([
                'সর্বমোট (Subtotal)' => '৳ ৩,৫০০',
                'ভ্যাট (VAT 5%)'     => '৳ ১৭৫',
                'প্রদেয় (Grand Total)' => '৳ ৩,৬৭৫',
            ])
            ->notes([
                'পণ্য ক্রয়ের জন্য ধন্যবাদ।',
                'Thank you for your business.',
            ])
            ->autoPrint(true);

        $html = $doc->render();

        $this->assertContains('INVOICE #INV-2026-99', $html);
        $this->assertContains('নবটেক লিমিটেড (Nobotech Ltd)', $html);
        $this->assertContains('মোঃ সোলায়মান আলী (Solayman Ali)', $html);
        $this->assertContains('ওয়েব হোস্টিং (Cloud VPS)', $html);
        $this->assertContains('প্রদেয় (Grand Total)', $html);
        $this->assertContains('window.print()', $html);
        $this->assertContains('@media print', $html);
        $this->assertContains('Noto Sans Bengali', $html);
    }

    public function testDetectHeadlessBinaryAndOptionalPdfExport(): void
    {
        $bin = Printable::detectHeadlessBinary();
        if ($bin) {
            $this->assertTrue(is_file($bin));

            // Test binary PDF generation
            $tmpPdf = sys_get_temp_dir() . '/bongo_test_inv_' . bin2hex(random_bytes(4)) . '.pdf';
            $doc = Printable::invoice('TEST-PDF-01')
                ->company(['name' => 'Nobotech'])
                ->table(['Item', 'Price'], [['Software', '$100']]);

            $success = $doc->toPdf($tmpPdf);
            if ($success) {
                $this->assertTrue(file_exists($tmpPdf));
                $this->assertTrue(filesize($tmpPdf) > 0);
                @unlink($tmpPdf);
            }
        } else {
            $this->assertNull($bin);
        }
    }
}
