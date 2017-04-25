<?php

class rex_ynewsletter extends \rex_yform_manager_dataset
{


    public function sendPackage( $size = 20)
    {
        if ($size == 0) {
            return $this->sendAll();
        }
        $users = $this->getUserOffset();
        $users = array_splice ( $users, 1, $size );
        return $this->send($users);
    }

    public function sendAll()
    {
        $users = $this->getUserOffset();
        return $this->send($users);
    }

    private function send($users)
    {
        if (count($users) == 0) {
            $this->setValue('status', 1)->save();
            return true;

        }

        $group = $this->getRelatedDataset('group');
        $article_id = $this->article_id;

        // TODO: noch sendtyp bauen [phpmailer/sendgrid] / abstract bauen
        // TODO: noch contenttyp bauen [article/yform-email-templates] / abstract bauen
        // TODO: normal (static), wie baue ich persönliche Newsletter (replace), wie ersetze ich userinfos am besten (replace), wie erstelle ich individuelle Newsletter am besten (php)
        //      userdaten in den Newsletter einspielen REX_VAR // REX_NEWSLETTER_USERDATA
        //      BE vs FE Problem beachten

        $article = new rex_article_content($article_id);
        $html_content = $article->getArticleTemplate();

        $plain_content = $article->getArticle();
        $plain_content = strip_tags($plain_content);
        $plain_content = html_entity_decode($plain_content);

        foreach($users as $user) {

            $email = $user[$group->email];

            // TODO: replace vars

            $mail = new rex_mailer();
            $mail->AddAddress($email);
            // TODO: AddAddressName
            $mail->From = $this->email_from;
            // TODO: $mail->FromName = $this->email_from_name;
            $mail->Subject = $this->subject;
            // TODO: $mail->AddAttachment($attachment, $name);
            $mail->Body = $plain_content;
            $mail->AltBody = $html_content;

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

            $this->ynewsletter_sent_count++;

        }

        return false;

    }


    public function getUserOffset()
    {
        $group = $this->getRelatedDataset('group');

        // build query
        $query = 'select * from `'.$group->table.'`';
        $group_filters = trim($group->filter);
        if ($group_filters != "") {
            foreach(explode("\n", $group_filters) as $group_filter) {
                $filter[] = '('.$group_filter.')';
            }
            $query .= ' where ' . implode(' and ', $filter);
        }
        foreach(rex_sql::factory()->getArray($query) as $q) {
            $send_list[$q['id']] = $q;
        }

        $this->ynewsletter_user_count = count($send_list);

        // get users from log
        $log_users = rex_ynewsletter_log::query()
            ->where('newsletter', $this->id)
            ->find();

        $this->ynewsletter_log_count = count($log_users);

        // remove log users from send_list
        $this->ynewsletter_sent_count = $this->ynewsletter_log_count;
        foreach($log_users as $log_user) {
            if (isset($send_list[$log_user->user_id])) {
                unset($send_list[$log_user->user_id]);
            }
        }

        return $send_list;

    }

}
