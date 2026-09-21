<?php

use PHPUnit\Framework\TestCase;

/**
 * Versandplanung: Termin, Fälligkeit und Versandsperre.
 * Die Sperr-Tests schreiben einen Newsletter in die Datenbank und löschen ihn im tearDown.
 *
 * @internal
 */
final class rex_ynewsletter_schedule_test extends TestCase
{
    /** @var list<rex_ynewsletter> */
    private array $created = [];
    private ?rex_ynewsletter_group $group = null;

    protected function tearDown(): void
    {
        foreach ($this->created as $newsletter) {
            $newsletter->delete();
        }
        $this->created = [];
        $this->group?->delete();
        $this->group = null;
    }

    /** @return iterable<string, array{mixed}> */
    public static function emptyDatetimeProvider(): iterable
    {
        yield 'leer' => [''];
        yield 'null' => [null];
        yield 'yform-nullwert' => ['0000-00-00 00:00:00'];
    }

    /** @dataProvider emptyDatetimeProvider */
    public function testEmptySendAtMeansManualSending(mixed $value): void
    {
        $newsletter = rex_ynewsletter::create()->setValue('status', 0)->setValue('send_at', $value);

        self::assertNull($newsletter->getSendAt());
        self::assertFalse($newsletter->isScheduled());
        self::assertFalse($newsletter->isDue());
    }

    public function testNewsletterWithoutSendAtColumnIsNotScheduled(): void
    {
        self::assertFalse(rex_ynewsletter::create()->isScheduled());
    }

    public function testDueDependsOnDateAndStatus(): void
    {
        $now = new DateTimeImmutable('2026-09-21 12:00:00');
        $newsletter = rex_ynewsletter::create()->setValue('status', 0)->setValue('send_at', '2026-09-21 12:00:00');

        self::assertTrue($newsletter->isScheduled());
        self::assertTrue($newsletter->isDue($now), 'Termin exakt erreicht');
        self::assertFalse($newsletter->isDue($now->modify('-1 second')), 'eine Sekunde vor dem Termin');
        self::assertTrue($newsletter->isDue($now->modify('+1 day')), 'lange nach dem Termin');

        $newsletter->setValue('status', 1);
        self::assertFalse($newsletter->isDue($now->modify('+1 day')), 'versendete Newsletter sind nie fällig');
    }

    public function testSendPackageRefusesScheduledNewsletterBeforeItsDate(): void
    {
        $newsletter = rex_ynewsletter::create()->setValue('status', 0)->setValue('send_at', '2999-01-01 00:00:00');

        $this->expectException(rex_exception::class);
        $newsletter->sendPackage(10);
    }

    public function testLockIsGrantedOnlyOnce(): void
    {
        $newsletter = $this->createNewsletter('2999-01-01 00:00:00');

        $first = new DateTimeImmutable('2026-09-21 12:00:00');

        self::assertFalse($newsletter->isLocked());
        self::assertTrue($newsletter->acquireSendLock($first), 'erster Lauf bekommt die Sperre');
        self::assertTrue($newsletter->isLocked());
        // anderer Zeitstempel: MySQL zählt nur geänderte Zeilen, ein gleicher Wert würde den Test blind machen
        self::assertFalse($newsletter->acquireSendLock($first->modify('+1 minute')), 'zweiter Lauf wird abgewiesen');

        // frisch aus der Datenbank gelesen
        $fromDb = rex_ynewsletter::query()->where('id', $newsletter->getId())->findOne();
        self::assertNotNull($fromDb);
        self::assertTrue($fromDb->isLocked());

        $newsletter->releaseSendLock();
        self::assertFalse($newsletter->isLocked());
        self::assertTrue($newsletter->acquireSendLock(), 'nach dem Aufheben wieder frei');
    }

    public function testGetDueReturnsOnlyOpenNewslettersWithReachedDate(): void
    {
        $past = $this->createNewsletter('2000-01-01 08:00:00');
        $future = $this->createNewsletter('2999-01-01 00:00:00');
        $manual = $this->createNewsletter(null);
        $sent = $this->createNewsletter('2000-01-01 08:00:00');
        $sent->setValue('status', 1)->save();

        $dueIds = array_map(static fn (rex_ynewsletter $n) => $n->getId(), iterator_to_array(rex_ynewsletter::getDue(), false));

        self::assertContains($past->getId(), $dueIds);
        self::assertNotContains($future->getId(), $dueIds);
        self::assertNotContains($manual->getId(), $dueIds);
        self::assertNotContains($sent->getId(), $dueIds);
    }

    private function createNewsletter(?string $sendAt): rex_ynewsletter
    {
        if (null === $this->group) {
            $this->group = rex_ynewsletter_group::create()
                ->setValue('name', 'phpunit schedule')
                ->setValue('table', rex::getTable('ynewsletter_log'))
                ->setValue('email', 'email')
                ->setValue('filter', '');
            self::assertTrue($this->group->save(), implode(' ', $this->group->getMessages()));
        }

        $newsletter = rex_ynewsletter::create()
            ->setValue('status', 0)
            ->setValue('subject', 'phpunit schedule')
            ->setValue('email_from', 'phpunit@example.invalid')
            ->setValue('email_from_name', 'phpunit')
            ->setValue('article_id', rex_article::getSiteStartArticleId())
            ->setValue('group', $this->group->getId())
            ->setValue('clang_id', rex_clang::getStartId())
            ->setValue('attachments', '')
            ->setValue('preheader', '')
            ->setValue('send_at', $sendAt ?? '0000-00-00 00:00:00');
        self::assertTrue($newsletter->save(), implode(' ', $newsletter->getMessages()));
        $this->created[] = $newsletter;

        return $newsletter;
    }
}
