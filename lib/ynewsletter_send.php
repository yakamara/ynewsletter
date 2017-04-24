<?php

class rex_ynewsletter_send {

    // erkennen welche Tabellen für den Userversand möglich sind
    // usertabelle: id,email rest optional

    // tracking vom versand // newsletter_id/email/user_id/versand ok // zeitstempel

    // Newsletter verarbeiten für den Versand
    // Artikelversand und YForm Templateversand ?



    //  - userdaten in den Newsletter einspielen REX_VAR // REX_NEWSLETTER_USERDATA
    //  - BE vs FE Problem beachten
    // Tracken von E-Mail Versand. // Obwohl das auch phpmailer macht
    //

    var $key = ''; // Kennung des speziellen Versandes
    var $template = []; // Template als array: subject, email_to, plain, html, attachments
    var $users = []; // Userarray: id, email .. optionen

    public function __construct($key, $template, $users)
    {
        self::setKey($key);
        self::setTemplate($template);
        self::setUsers($users);

    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    public function setTemplate($template)
    {
        $this->template = $template;
    }

    public function setUsers($users)
    {
        $this->users = $users;
    }

    public function getUserDiff()
    {
        // TODO:


    }


    public function send()
    {
        $users = $this->getUserDiff();



    }



}