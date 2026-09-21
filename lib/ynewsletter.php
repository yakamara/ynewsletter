<?php

use Sprog\Wildcard;

class rex_ynewsletter extends rex_yform_manager_dataset
{
    public $ynewsletter_log_count;
    public $ynewsletter_sent_count;
    public $ynewsletter_user_count;

    /** @var self|null Newsletter, der gerade versendet wird */
    private static $currentSending;

    /**
     * True, solange send() läuft. Templates und Module können damit im normalen
     * Seitenaufruf einen Standardtext ausgeben, während im Versand die
     * REX_YNEWSLETTER_*-Platzhalter aufgelöst werden (#44).
     */
    public static function isSending(): bool
    {
        return null !== self::$currentSending;
    }

    public static function getCurrentSending(): ?self
    {
        return self::$currentSending;
    }

    /**
     * Versendet ein Paket. Rückgabe true = nichts mehr zu tun (Status auf versendet gesetzt),
     * false = Paket verschickt, weitere folgen.
     *
     * @throws rex_exception wenn ein Versandtermin gesetzt und noch nicht erreicht ist
     */
    public function sendPackage($size = 20)
    {
        if ($this->isScheduled() && !$this->isDue()) {
            throw new rex_exception('Newsletter [id=' . $this->getId() . '] is scheduled for ' . $this->getSendAt() . ' and not due yet');
        }

        if (0 == $size) {
            return $this->sendAll();
        }
        $users = $this->getUserOffset();
        $users = array_splice($users, 0, $size);

        return $this->send($users);
    }

    public function sendAll()
    {
        $users = $this->getUserOffset();
        return $this->send($users);
    }

    /**
     * Versendet paketweise, bis keine Empfänger mehr offen sind. Für Konsole und Cronjob;
     * im Browser übernimmt der Seiten-Reload diese Schleife.
     *
     * @param callable(self):void|null $afterPackage wird nach jedem Paket aufgerufen (Fortschritt)
     */
    public function sendComplete(int $packageSize = 100, int $delaySeconds = 0, ?callable $afterPackage = null): void
    {
        $packageSize = max(1, $packageSize);
        while (!$this->sendPackage($packageSize)) {
            if (null !== $afterPackage) {
                $afterPackage($this);
            }
            if ($delaySeconds > 0) {
                sleep($delaySeconds);
            }
        }
    }

    /**
     * Versendet alle fälligen Newsletter (Termin erreicht, Status offen). Jeder Newsletter wird
     * über sending_started_at gesperrt, damit sich überlappende Läufe nicht in die Quere kommen.
     *
     * @return list<string> Meldungen je Newsletter
     */
    public static function sendDue(int $packageSize = 100, int $delaySeconds = 0): array
    {
        $messages = [];
        $due = self::getDue();
        if (0 === count($due)) {
            $messages[] = rex_i18n::rawMsg('ynewsletter_console_nothing_due');
            return $messages;
        }

        foreach ($due as $newsletter) {
            if (!$newsletter->acquireSendLock()) {
                $messages[] = rex_i18n::rawMsg('ynewsletter_console_locked', $newsletter->getId(), (string) $newsletter->getSendingStartedAt());
                continue;
            }

            try {
                $newsletter->sendComplete($packageSize, $delaySeconds);
            } finally {
                $newsletter->releaseSendLock();
            }

            $messages[] = rex_i18n::rawMsg(
                'ynewsletter_console_sent',
                $newsletter->getId(),
                (string) $newsletter->getValue('subject'),
                (int) $newsletter->ynewsletter_sent_count,
                (int) $newsletter->ynewsletter_user_count,
                rex_i18n::rawMsg(1 == $newsletter->getValue('status') ? 'ynewsletter_status_sent' : 'ynewsletter_status_open'),
            );
        }

        return $messages;
    }

    /**
     * Offene Newsletter mit erreichtem Versandtermin.
     *
     * @return rex_yform_manager_collection<static>
     */
    public static function getDue(?DateTimeInterface $now = null): rex_yform_manager_collection
    {
        $now ??= new DateTimeImmutable();

        return self::query()
            ->where('status', 0)
            ->whereRaw('`send_at` IS NOT NULL AND `send_at` NOT LIKE "0000-00-00%" AND `send_at` <= :now', ['now' => $now->format(rex_sql::FORMAT_DATETIME)])
            ->orderBy('send_at')
            ->find();
    }

    /**
     * Versandtermin als "Y-m-d H:i:s" oder null. YForm speichert ein leeres datetime-Feld
     * als "0000-00-00 00:00:00", das gilt hier als nicht gesetzt.
     */
    public function getSendAt(): ?string
    {
        return $this->readDatetime('send_at');
    }

    /** Termin gesetzt: Versand läuft über Konsole oder Cronjob, nicht über den Browser. */
    public function isScheduled(): bool
    {
        return null !== $this->getSendAt();
    }

    /** Termin erreicht und Newsletter noch offen. Der Vergleich läuft in PHP, nicht in MySQL (Zeitzonen). */
    public function isDue(?DateTimeInterface $now = null): bool
    {
        $sendAt = $this->getSendAt();
        if (null === $sendAt || 1 == $this->getValue('status')) {
            return false;
        }
        $now ??= new DateTimeImmutable();

        return $sendAt <= $now->format(rex_sql::FORMAT_DATETIME);
    }

    /** Beginn des laufenden Versands (Sperre) oder null. */
    public function getSendingStartedAt(): ?string
    {
        return $this->readDatetime('sending_started_at');
    }

    public function isLocked(): bool
    {
        return null !== $this->getSendingStartedAt();
    }

    /**
     * Setzt die Versandsperre atomar: Das UPDATE greift nur, wenn noch keine Sperre steht,
     * sodass von zwei parallelen Läufen genau einer gewinnt.
     */
    public function acquireSendLock(?DateTimeInterface $now = null): bool
    {
        $now = ($now ?? new DateTimeImmutable())->format(rex_sql::FORMAT_DATETIME);
        $sql = rex_sql::factory();
        $sql->setQuery(
            'UPDATE `' . rex::getTable('ynewsletter') . '`
                SET `sending_started_at` = :now
              WHERE `id` = :id
                AND (`sending_started_at` IS NULL OR `sending_started_at` LIKE "0000-00-00%")',
            ['now' => $now, 'id' => $this->getId()],
        );

        if (1 !== $sql->getRows()) {
            return false;
        }
        $this->setValue('sending_started_at', $now);

        return true;
    }

    /** Hebt die Versandsperre auf, auch von der Versandseite aus für abgebrochene Läufe. */
    public function releaseSendLock(): void
    {
        rex_sql::factory()->setQuery(
            'UPDATE `' . rex::getTable('ynewsletter') . '` SET `sending_started_at` = NULL WHERE `id` = :id',
            ['id' => $this->getId()],
        );
        $this->setValue('sending_started_at', null);
    }

    private function readDatetime(string $column): ?string
    {
        if (!$this->hasValue($column)) {
            return null;
        }
        $value = trim((string) $this->getValue($column));
        if ('' === $value || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        return $value;
    }

    public function send($users)
    {
        if (0 == count($users)) {
            $this->setValue('status', 1)->save();
            return true;
        }

        $UserGroup = $this->getGroup();
        $article_id = (int) $this->getValue('article_id');

        // Sprache des Newsletters für den gesamten Versand als aktuelle Sprache setzen,
        // damit Templates mit rex_clang::getCurrent() dieselbe Sprache sehen wie der Artikel (#58)
        $clang_id = (int) $this->getValue('clang_id');
        if (!$clang_id || !rex_clang::exists($clang_id)) {
            $clang_id = rex_clang::getCurrentId();
        }
        $previousClangId = rex_clang::getCurrentId();
        rex_clang::setCurrentId($clang_id);
        self::$currentSending = $this;

        try {
            // TODO: noch contenttyp bauen [article/yform-email-templates] / abstract bauen

            $article = new rex_article_content($article_id, $clang_id);
            $Body = $article->getArticleTemplate();

            $AltBody = $article->getArticle();
            $AltBody = strip_tags($AltBody);
            $AltBody = html_entity_decode($AltBody);

            $Subject = (string) $this->getValue('subject');
            // hasValue: Spalte fehlt, solange das Tableset nach dem Update noch nicht neu importiert wurde
            $Preheader = $this->hasValue('preheader') ? trim((string) $this->getValue('preheader')) : '';

            $mediaList = [];
            if ('' != $this->getValue('attachments')) {
                foreach (explode(',', $this->getValue('attachments')) as $mediaFilename) {
                    $media = rex_media::get($mediaFilename);
                    if ($media) {
                        $mediaList[] = $media;
                    }
                }
            }

            foreach ($users as $user) {
                $email = $user[$UserGroup->getEMailField()];

                $mail = new rex_mailer();
                foreach ($mediaList as $media) {
                    $mail->addAttachment(rex_path::media($media->getFileName()), $media->getOriginalFileName());
                }

                $mail->AddAddress($email);
                // TODO: AddAddressName
                $mail->From = (string) $this->getValue('email_from');
                $mail->FromName = (string) $this->getValue('email_from_name');

                $mail->Subject = $this->parseContent($Subject, $user, $UserGroup, $clang_id);
                $mail->AltBody = self::optimizeTextBody($this->parseContent($AltBody, $user, $UserGroup, $clang_id));

                $BodyUser = $this->parseContent($Body, $user, $UserGroup, $clang_id);
                if ('' !== $Preheader) {
                    $BodyUser = self::injectPreheader($BodyUser, $this->parseContent($Preheader, $user, $UserGroup, $clang_id));
                }
                $mail->Body = $BodyUser;

                $epParams = [
                    'newsletter' => $this,
                    'group' => $UserGroup,
                    'user' => $user,
                    'email' => $email,
                ];

                // Letzte Möglichkeit, die Mail zu verändern (Tracking, Header, eigene Platzhalter).
                // Liefert der EP kein rex_mailer-Objekt zurück, wird die Mail nicht verschickt,
                // aber als fehlgeschlagen geloggt, damit der Paketversand nicht hängen bleibt (#39).
                /** @var mixed $mail */
                $mail = rex_extension::registerPoint(new rex_extension_point('YNEWSLETTER_MAIL_BEFORE_SEND', $mail, $epParams));

                $status = 0;
                if ($mail instanceof rex_mailer && $mail->Send()) {
                    $status = 1;
                }

                rex_extension::registerPoint(new rex_extension_point('YNEWSLETTER_MAIL_SENT', $status, $epParams + ['mail' => $mail]));

                // add to log
                $log = rex_ynewsletter_log::create()
                    ->setValue('user_id', $user['id'])
                    ->setValue('newsletter', $this->getId())
                    ->setValue('email', $email)
                    ->setValue('status', $status)
                    ->save();

                ++$this->ynewsletter_sent_count;
            }
        } finally {
            self::$currentSending = null;
            rex_clang::setCurrentId($previousClangId);
        }

        return false;
    }

    /**
     * Löst REX_YNEWSLETTER_*-Platzhalter und eingebettetes PHP für einen Empfänger auf
     * und ersetzt anschließend Sprog-Platzhalter ({{ … }}), sofern Sprog installiert ist.
     */
    private function parseContent(string $content, array $user, rex_ynewsletter_group $group, int $clangId): string
    {
        $content = rex_var::parse($content, rex_var::ENV_OUTPUT, 'ynewsletter_template', ['user' => $user, 'group' => $group]);
        $content = rex_file::getOutput(rex_stream::factory('ynewsletter/plain_content', $content));

        return self::parseWildcards($content, $clangId);
    }

    /**
     * Sprog ersetzt {{ platzhalter }} nur über den OUTPUT_FILTER im Frontend, den der
     * Mailversand nie durchläuft. Deshalb hier explizit aufrufen (#38, #58).
     */
    public static function parseWildcards(string $content, int $clangId): string
    {
        if (class_exists(Wildcard::class)) {
            return Wildcard::parse($content, $clangId);
        }

        return $content;
    }

    /**
     * Fügt den Preheader unsichtbar direkt nach dem öffnenden body-Tag ein.
     * Fehlt ein body-Tag, wird er vorangestellt (#41).
     */
    public static function injectPreheader(string $html, string $preheader): string
    {
        $div = '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:transparent;opacity:0;">' . rex_escape($preheader) . '</div>';

        $count = 0;
        $result = preg_replace_callback('/<body\b[^>]*>/i', static function (array $match) use ($div) {
            return $match[0] . $div;
        }, $html, 1, $count);

        return $count > 0 ? $result : $div . $html;
    }

    /**
     * Versandgruppe des Newsletters. Wirft, wenn keine Gruppe zugeordnet ist,
     * denn ohne Gruppe gibt es keine Empfängertabelle und kein E-Mail-Feld.
     */
    public function getGroup(): rex_ynewsletter_group
    {
        $group = $this->getRelatedDataset('group');
        if (!$group instanceof rex_ynewsletter_group) {
            throw new rex_exception('Newsletter [id=' . $this->getId() . '] has no group');
        }

        return $group;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUsers()
    {
        return $this->getGroup()->getAllUsers();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUserOffset()
    {
        $Users = $this->getUsers();
        $filteredUsers = $this->getGroup()->filterExclusions($Users);

        $this->ynewsletter_user_count = count($filteredUsers);

        // get users from log
        $log_users = rex_ynewsletter_log::query()
            ->where('newsletter', $this->getId())
            ->find();

        $this->ynewsletter_log_count = count($log_users);

        // remove log users from send_list
        $this->ynewsletter_sent_count = $this->ynewsletter_log_count;
        foreach ($log_users as $log_user) {
            $userId = (int) $log_user->getValue('user_id');
            if (isset($filteredUsers[$userId])) {
                unset($filteredUsers[$userId]);
            }
        }

        return $filteredUsers;
    }

    public function deleteUserFromLog(array $user)
    {
        $userObject = rex_ynewsletter_log::query()
            ->where('user_id', $user['id'])
            ->where('newsletter', $this->getId())
            ->findOne();
        if ($userObject) {
            $userObject->delete();
            return true;
        }
        return false;
    }

    public static function optimizeTextBody($str)
    {
        $str = str_replace("\r", '', $str);
        $str = preg_replace("/[ \n]{2,}/", "\n\n", $str);
        // otherwise message_type would be plain and template code will be sent as message
        return '' == $str ? ' ' : $str;
    }

    public static function getEncryptionKey()
    {
        $encryption_key = rex_config::get('ynewsletter', 'encryption_key');
        if (!$encryption_key || '' == $encryption_key) {
            $encryption_key = bin2hex(random_bytes(64));
            rex_config::set('ynewsletter', 'encryption_key', $encryption_key);
        }
        return $encryption_key;
    }

    public static function encrypt($value)
    {
        $string = serialize($value);
        $encrypted_string = openssl_encrypt($string, 'AES-128-ECB', self::getEncryptionKey());
        return $encrypted_string;
    }

    public static function decryptString($string)
    {
        $decrypted_string = openssl_decrypt($string, 'AES-128-ECB', self::getEncryptionKey());
        $value = unserialize($decrypted_string);
        return $value;
    }
}
