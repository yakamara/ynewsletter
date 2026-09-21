<?php

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class rex_ynewsletter_text_body_test extends TestCase
{
    public function testCarriageReturnsAreRemovedAndWhitespaceIsCollapsed(): void
    {
        $text = "Zeile 1\r\n\r\n\r\n   Zeile 2\n\n\n\nZeile 3";

        self::assertSame("Zeile 1\n\nZeile 2\n\nZeile 3", rex_ynewsletter::optimizeTextBody($text));
    }

    public function testEmptyTextBodyBecomesSingleSpace(): void
    {
        // Ein leerer AltBody würde PHPMailer auf message_type "plain" schalten
        self::assertSame(' ', rex_ynewsletter::optimizeTextBody(''));
    }

    public function testSendingStateIsFalseOutsideOfSend(): void
    {
        self::assertFalse(rex_ynewsletter::isSending());
        self::assertNull(rex_ynewsletter::getCurrentSending());
    }
}
