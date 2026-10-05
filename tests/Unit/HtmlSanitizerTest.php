<?php

declare(strict_types=1);

namespace Tests\Unit;

use System\Security\HtmlSanitizer;
use System\Testing\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function testEmptyOrNoHtmlReturnsCleanString(): void
    {
        $this->assertSame('', HtmlSanitizer::clean(''));
        $this->assertSame('Hello World', HtmlSanitizer::clean('Hello World'));
        $this->assertSame('1 &lt; 2 &amp; 3 &gt; 0', HtmlSanitizer::clean('1 < 2 & 3 > 0'));
    }

    public function testStripsScriptTagsAndDangerousElements(): void
    {
        $this->assertSame('', HtmlSanitizer::clean('<script>alert("xss")</script>'));
        $this->assertSame('', HtmlSanitizer::clean('<style>body { display: none; }</style>'));
        $this->assertSame('', HtmlSanitizer::clean('<iframe src="https://evil.com"></iframe>'));
        $this->assertSame('', HtmlSanitizer::clean('<object data="malware.swf"></object>'));
        $this->assertSame('', HtmlSanitizer::clean('<embed src="malware.swf">'));
        $this->assertSame('', HtmlSanitizer::clean('<svg onload="alert(1)"></svg>'));
        $this->assertSame('', HtmlSanitizer::clean('<form action="/steal"><input name="pass"></form>'));
    }

    public function testStripsInlineEventHandlers(): void
    {
        $html = '<img src="https://example.com/photo.jpg" onerror="alert(1)" onload="alert(2)" alt="Photo">';
        $cleaned = HtmlSanitizer::clean($html);
        $this->assertNotContains('onerror', $cleaned);
        $this->assertNotContains('onload', $cleaned);
        $this->assertContains('src="https://example.com/photo.jpg"', $cleaned);
        $this->assertContains('alt="Photo"', $cleaned);

        $htmlLink = '<a href="https://example.com" onclick="stealCookies()" onmouseover="track()">Link</a>';
        $cleanedLink = HtmlSanitizer::clean($htmlLink);
        $this->assertNotContains('onclick', $cleanedLink);
        $this->assertNotContains('onmouseover', $cleanedLink);
        $this->assertContains('href="https://example.com"', $cleanedLink);
    }

    public function testStripsJavascriptAndDangerousProtocols(): void
    {
        $this->assertSame('<a>Click</a>', HtmlSanitizer::clean('<a href="javascript:alert(1)">Click</a>'));
        $this->assertSame('<a>Click</a>', HtmlSanitizer::clean('<a href="vbscript:msgbox(1)">Click</a>'));
        $this->assertSame('<a>Click</a>', HtmlSanitizer::clean('<a href="  javascript:alert(1)">Click</a>'));
        $this->assertSame('<a>Click</a>', HtmlSanitizer::clean('<a href="java&#09;script:alert(1)">Click</a>'));
        $this->assertSame('<img>', HtmlSanitizer::clean('<img src="javascript:alert(1)">'));
    }

    public function testAddsNoopenerNoreferrerToBlankTargets(): void
    {
        $html = '<a href="https://example.com" target="_blank">External</a>';
        $cleaned = HtmlSanitizer::clean($html);
        $this->assertContains('target="_blank"', $cleaned);
        $this->assertContains('rel="noopener noreferrer"', $cleaned);
    }

    public function testStripsDangerousCssInStyleAttribute(): void
    {
        $html = '<p style="color: red; width: expression(alert(1))">Text</p>';
        $cleaned = HtmlSanitizer::clean($html);
        $this->assertNotContains('expression', $cleaned);
        $this->assertNotContains('style', $cleaned);

        $htmlUrl = '<div style="background-image: url(javascript:alert(1))">Test</div>';
        $this->assertNotContains('javascript', HtmlSanitizer::clean($htmlUrl));
    }

    public function testPreservesSafeFormattingAndBengaliUnicode(): void
    {
        $input = '<p>স্বাগতম <strong>বাংলাদেশ</strong>! আমাদের <a href="https://bongo.dev">ওয়েবসাইট</a> দেখুন।</p>';
        $this->assertSame($input, HtmlSanitizer::clean($input));

        $formatted = '<h1>Title</h1><p>Paragraph with <em>emphasis</em> and <code>code</code>.</p>';
        $this->assertSame($formatted, HtmlSanitizer::clean($formatted));

        $table = '<table class="table"><thead><tr><th>Name</th></tr></thead><tbody><tr><td>Item</td></tr></tbody></table>';
        $this->assertSame($table, HtmlSanitizer::clean($table));
    }

    public function testGlobalCleanHtmlHelper(): void
    {
        $this->assertSame(
            '<p>Safe text</p>',
            clean_html('<p>Safe text</p><script>alert(1)</script>')
        );
    }
}
