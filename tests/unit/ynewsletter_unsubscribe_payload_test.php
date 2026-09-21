<?php

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class rex_ynewsletter_unsubscribe_payload_test extends TestCase
{
    public function testPayloadRoundtripKeepsRedirectTarget(): void
    {
        $payload = ['email' => 'a@example.org', 'groups' => '1,3', 'redirectToID' => 3, 'redirectTo' => 'https://example.org/bye'];

        $decoded = rex_ynewsletter::decryptString(rex_ynewsletter::encrypt($payload));

        self::assertSame($payload, $decoded);
    }

    public function testPayloadWithoutRedirectToFromOlderVersionsIsStillReadable(): void
    {
        $decoded = rex_ynewsletter::decryptString(rex_ynewsletter::encrypt(['email' => 'a@example.org', 'groups' => '1', 'redirectToID' => 3]));

        self::assertIsArray($decoded);
        self::assertArrayNotHasKey('redirectTo', $decoded);
        self::assertEmpty($decoded['redirectTo'] ?? null);
    }

    public function testGarbageTokenDoesNotDecodeToAnArray(): void
    {
        self::assertNotTrue(is_array(rex_ynewsletter::decryptString('foo_bar')));
        self::assertNotTrue(is_array(rex_ynewsletter::decryptString('')));
    }

    public function testEncryptionKeyIsStableBetweenCalls(): void
    {
        self::assertSame(rex_ynewsletter::getEncryptionKey(), rex_ynewsletter::getEncryptionKey());
        self::assertSame(128, strlen(rex_ynewsletter::getEncryptionKey()));
    }
}
