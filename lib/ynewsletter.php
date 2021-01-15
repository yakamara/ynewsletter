<?php

class rex_ynewsletter extends \rex_yform_manager_dataset
{
    public $ynewsletter_log_count;
    public $ynewsletter_sent_count;
    public $ynewsletter_user_count;

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

        $group = $this->getRelatedDataset('group');
        $article_id = $this->article_id;
        $clang_id = $this->clang_id;

        // TODO: noch contenttyp bauen [article/yform-email-templates] / abstract bauen

        $article = new rex_article_content($article_id, $clang_id);
        $Body = $article->getArticleTemplate();

        $AltBody = $article->getArticle();
        $AltBody = strip_tags($AltBody);
        $AltBody = html_entity_decode($AltBody);

        $Subject = $this->subject;

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
            $email = $user[$group->email];

            $mail = new rex_mailer();
            foreach ($mediaList as $media) {
                $mail->addAttachment(rex_url::media($media->getFileName()), $media->getOriginalFileName());
            }

            $mail->AddAddress($email);
            // TODO: AddAddressName
            $mail->From = $this->email_from;
            $mail->FromName = $this->email_from_name;

            $SubjectUser = rex_var::parse($Subject, rex_var::ENV_OUTPUT, 'ynewsletter_template', $user);
            $SubjectUser = rex_file::getOutput(rex_stream::factory('ynewsletter/plain_content', $SubjectUser));
            $mail->Subject = $SubjectUser;

            $AltBodyUser = rex_var::parse($AltBody, rex_var::ENV_OUTPUT, 'ynewsletter_template', $user);
            $AltBodyUser = rex_file::getOutput(rex_stream::factory('ynewsletter/plain_content', $AltBodyUser));
            $mail->AltBody = self::optimizeTextBody($AltBodyUser);

            $BodyUser = rex_var::parse($Body, rex_var::ENV_OUTPUT, 'ynewsletter_template', $user);
            $BodyUser = rex_file::getOutput(rex_stream::factory('ynewsletter/plain_content', $BodyUser));
            $mail->Body = $BodyUser;

            $status = 0;
            if ($mail->Send()) {
                $status = 1;
            }

            // add to log
            $log = rex_ynewsletter_log::create()
                ->setValue('user_id', $user['id'])
                ->setValue('newsletter', $this->id)
                ->setValue('email', $email)
                ->setValue('status', $status)
                ->save();

            ++$this->ynewsletter_sent_count;
        }

        return false;
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
}
