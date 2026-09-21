<?php

class rex_ynewsletter extends \rex_yform_manager_dataset
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

    public function sendPackage($size = 20)
    {
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

    public function send($users)
    {
        if (0 == count($users)) {
            $this->setValue('status', 1)->save();
            return true;
        }

        /** @var rex_ynewsletter_group $UserGroup */
        $UserGroup = $this->getRelatedDataset('group');
        $article_id = (int) $this->article_id;

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

            $Subject = $this->subject;
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
                $mail->From = $this->email_from;
                $mail->FromName = $this->email_from_name;

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
                $mail = rex_extension::registerPoint(new rex_extension_point('YNEWSLETTER_MAIL_BEFORE_SEND', $mail, $epParams));

                $status = 0;
                if ($mail instanceof rex_mailer && $mail->Send()) {
                    $status = 1;
                }

                rex_extension::registerPoint(new rex_extension_point('YNEWSLETTER_MAIL_SENT', $status, $epParams + ['mail' => $mail]));

                // add to log
                $log = rex_ynewsletter_log::create()
                    ->setValue('user_id', $user['id'])
                    ->setValue('newsletter', $this->id)
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
        if (class_exists(\Sprog\Wildcard::class)) {
            return \Sprog\Wildcard::parse($content, $clangId);
        }

        return $content;
    }

    /**
     * Fügt den Preheader unsichtbar direkt nach dem öffnenden body-Tag ein.
     * Fehlt ein body-Tag, wird er vorangestellt (#41).
     */
    public static function injectPreheader(string $html, string $preheader): string
    {
        $div = '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:transparent;opacity:0;">'.rex_escape($preheader).'</div>';

        $count = 0;
        $result = preg_replace_callback('/<body\b[^>]*>/i', static function (array $match) use ($div) {
            return $match[0].$div;
        }, $html, 1, $count);

        return $count > 0 ? $result : $div.$html;
    }

    public function getUsers()
    {
        return $this->getRelatedDataset('group')->getAllUsers();
    }

    public function getUserOffset()
    {
        $Users = $this->getUsers();
        $group = $this->getRelatedDataset('group');
        $filteredUsers = $group->filterExclusions($Users);

        $this->ynewsletter_user_count = count($filteredUsers);

        // get users from log
        $log_users = rex_ynewsletter_log::query()
            ->where('newsletter', $this->getId())
            ->find();

        $this->ynewsletter_log_count = count($log_users);

        // remove log users from send_list
        $this->ynewsletter_sent_count = $this->ynewsletter_log_count;
        foreach ($log_users as $log_user) {
            if (isset($filteredUsers[$log_user->user_id])) {
                unset($filteredUsers[$log_user->user_id]);
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
