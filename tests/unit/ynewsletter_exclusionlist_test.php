<?php

use PHPUnit\Framework\TestCase;

/**
 * Schreibt echte Datensätze in rex_ynewsletter_group und rex_ynewsletter_exclusionlist
 * und räumt sie im tearDown wieder weg.
 *
 * @internal
 */
final class rex_ynewsletter_exclusionlist_test extends TestCase
{
    private const MAIL_GROUP = 'phpunit-gruppe@example.invalid';
    private const MAIL_GLOBAL = 'phpunit-global@example.invalid';
    private const MAIL_OTHER = 'phpunit-andere@example.invalid';

    /** @var rex_ynewsletter_group */
    private $group;
    /** @var rex_ynewsletter_group */
    private $otherGroup;

    protected function setUp(): void
    {
        $this->group = rex_ynewsletter_group::create()
            ->setValue('name', 'phpunit A')
            ->setValue('table', rex::getTable('ynewsletter_log'))
            ->setValue('email', 'email')
            ->setValue('filter', '');
        $this->group->save();

        $this->otherGroup = rex_ynewsletter_group::create()
            ->setValue('name', 'phpunit B')
            ->setValue('table', rex::getTable('ynewsletter_log'))
            ->setValue('email', 'email')
            ->setValue('filter', '');
        $this->otherGroup->save();

        rex_ynewsletter_exclusionlist::excludeEMail(self::MAIL_GROUP, (string) $this->group->getId());
        rex_ynewsletter_exclusionlist::excludeEMail(self::MAIL_GLOBAL, '');
        rex_ynewsletter_exclusionlist::excludeEMail(self::MAIL_OTHER, (string) $this->otherGroup->getId());
    }

    protected function tearDown(): void
    {
        foreach ([self::MAIL_GROUP, self::MAIL_GLOBAL, self::MAIL_OTHER] as $email) {
            foreach (rex_ynewsletter_exclusionlist::query()->where('email', $email)->find() as $entry) {
                $entry->delete();
            }
        }
        $this->group->delete();
        $this->otherGroup->delete();
    }

    public function testExcludeEMailCreatesOneEntryPerGroup(): void
    {
        $email = 'phpunit-mehrfach@example.invalid';
        rex_ynewsletter_exclusionlist::excludeEMail($email, $this->group->getId() . ',' . $this->otherGroup->getId());

        $entries = rex_ynewsletter_exclusionlist::query()->where('email', $email)->find();
        self::assertCount(2, $entries);
        foreach ($entries as $entry) {
            self::assertSame('unsubscribe', $entry->getValue('type'));
            $entry->delete();
        }
    }

    public function testGroupSeesOwnAndGlobalExclusionsButNotOtherGroups(): void
    {
        $emails = $this->group->getExclusionsEMails();

        self::assertContains(self::MAIL_GROUP, $emails);
        self::assertContains(self::MAIL_GLOBAL, $emails);
        self::assertNotContains(self::MAIL_OTHER, $emails);
    }

    public function testFilterExclusionsIgnoresCase(): void
    {
        $users = [
            1 => ['id' => 1, 'email' => strtoupper(self::MAIL_GROUP)],
            2 => ['id' => 2, 'email' => self::MAIL_GLOBAL],
            3 => ['id' => 3, 'email' => self::MAIL_OTHER],
            4 => ['id' => 4, 'email' => 'phpunit-bleibt@example.invalid'],
        ];

        $remaining = $this->group->filterExclusions($users);

        self::assertSame([3, 4], array_keys($remaining));
    }
}
