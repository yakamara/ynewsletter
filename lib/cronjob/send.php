<?php

/**
 * Cronjob-Typ "YNewsletter: geplante Newsletter versenden".
 * Gleiche Logik wie bin/console ynewsletter:send, für Installationen ohne System-Cron.
 */
class rex_ynewsletter_cronjob_send extends rex_cronjob
{
    public function execute()
    {
        $messages = rex_ynewsletter::sendDue((int) $this->getParam('package_size', 100));
        $this->setMessage(implode("\n", $messages));

        return true;
    }

    public function getTypeName()
    {
        return rex_i18n::msg('ynewsletter_cronjob_send');
    }

    public function getParamFields()
    {
        return [
            [
                'label' => rex_i18n::msg('ynewsletter_cronjob_package_size'),
                'name' => 'package_size',
                'type' => 'text',
                'default' => '100',
                'notice' => rex_i18n::msg('ynewsletter_cronjob_package_size_notice'),
            ],
        ];
    }
}
