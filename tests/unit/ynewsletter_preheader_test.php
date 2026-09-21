<?php

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class rex_ynewsletter_preheader_test extends TestCase
{
    public function testPreheaderIsInsertedDirectlyAfterBodyTag(): void
    {
        $html = '<html><head></head><body class="x" style="margin:0"><p>Inhalt</p></body></html>';

        $result = rex_ynewsletter::injectPreheader($html, 'Vorschau');

        self::assertStringStartsWith('<html><head></head><body class="x" style="margin:0"><div style="display:none;', $result);
        self::assertStringEndsWith('<p>Inhalt</p></body></html>', $result);
        self::assertSame(1, substr_count($result, 'display:none;max-height:0'));
    }

    public function testPreheaderIsEscapedAndReplacementPatternsAreKeptLiterally(): void
    {
        $result = rex_ynewsletter::injectPreheader('<body>x</body>', 'Hallo <b>Welt</b> & $1 \\1');

        self::assertStringContainsString('Hallo &lt;b&gt;Welt&lt;/b&gt; &amp; $1 \\1', $result);
    }

    public function testPreheaderIsPrependedWhenBodyTagIsMissing(): void
    {
        $result = rex_ynewsletter::injectPreheader('<p>ohne body</p>', 'Vorschau');

        self::assertStringStartsWith('<div style="display:none;', $result);
        self::assertStringEndsWith('<p>ohne body</p>', $result);
    }
}
