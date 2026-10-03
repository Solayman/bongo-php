<?php
namespace Tests\Unit;

use System\Framework;
use System\Testing\TestCase;

class FrameworkTest extends TestCase
{
    public function testVersionIsSemantic(): void
    {
        $this->assertTrue((bool) preg_match('/^\d+\.\d+\.\d+(-[\w.]+)?$/', Framework::version()), 'version: ' . Framework::version());
    }

    public function testSizeCountsTheFrameworkCode(): void
    {
        $size = Framework::size();
        $this->assertTrue($size['files'] > 50 && $size['lines'] > 5000 && $size['kb'] > 100, json_encode($size));
    }
}
