<?php

class rex_ynewsletter_exclusionlist extends \rex_yform_manager_dataset
{
    public static $unsubscribeKey = 'rex_ynewsletter_unsubscribe';

    public static function getByGroupId(int $groupId)
    {
        return self::query()
            ->where('group', $groupId)
            ->where('group', '')
            ->setWhereOperator('OR')
            ->groupBy('email')
            ->find();
    }

    public static function excludeEMail($Email, $groupIDs = '')
    {
        foreach (explode(',', $groupIDs) as $groupID) {
            self::create()
                ->setValue('email', $Email)
                ->setValue('group', $groupID)
                ->setValue('type', 'unsubscribe')
                ->save();
        }
    }

    /**
     * $redirectTo (absolute URL) hat Vorrang vor $redirectToID (Artikel-ID).
     * Die Weiterleitung steckt verschlüsselt im Link; Links aus älteren Versionen
     * ohne redirectTo bleiben gültig.
     */
    public static function getUnsubscribeUrl($userEmail, $groups, $redirectToID, string $redirectTo = ''): string
    {
        $a = [
            'email' => $userEmail,
            'groups' => $groups,
            'redirectToID' => $redirectToID,
            'redirectTo' => $redirectTo,
        ];

        $a_encrypted = rex_ynewsletter::encrypt($a);
        $domain = rex_yrewrite::getCurrentDomain();

        return $domain->getUrl().'?'.self::$unsubscribeKey.'='.urlencode($a_encrypted);
    }

    public static function initExclude()
    {
        if (!rex_request(self::$unsubscribeKey, 'string', null)) {
            return;
        }

        $UserInfoString = rex_request(self::$unsubscribeKey, 'string');
        $UserInfo = rex_ynewsletter::decryptString($UserInfoString);
        if (is_array($UserInfo)) {
            self::excludeEMail($UserInfo['email'], $UserInfo['groups']);
            if (!empty($UserInfo['redirectTo'])) {
                rex_response::sendRedirect($UserInfo['redirectTo']);
            }
            rex_response::sendRedirect(rex_getUrl($UserInfo['redirectToID'], '', [], '&'));
        }
    }
}
