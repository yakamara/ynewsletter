<?php

/**
 * REX_YNEWSLETTER_UNSUBSCRIBE[groups=1,3,2 redirectToID=3 output=url]
 * REX_YNEWSLETTER_UNSUBSCRIBE[redirectTo="https://example.org/abgemeldet" output=url].
 */
class rex_var_ynewsletter_unsubscribe extends rex_var
{
    protected function getOutput()
    {
        if (!in_array($this->getContext(), ['ynewsletter_template'])) {
            return false;
        }

        $ContextData = $this->getContextData();
        $User = $ContextData['user'];

        /** @var rex_ynewsletter_group $UserGroup */
        $UserGroup = $ContextData['group'];

        // Ziel nach der Abmeldung: entweder eine absolute URL (redirectTo, #32) oder ein Artikel (redirectToID)
        $redirectTo = trim((string) $this->getArg('redirectTo', ''));
        $redirectToID = $this->getArg('redirectToID') ?? rex_yrewrite::getCurrentDomain()->getStartId();

        if ('' === $redirectTo && !$redirectToID) {
            return self::quote('redirectToID or redirectTo attribute is missing');
        }

        $groups = $this->getArg('groups') ?? null;
        if (!$groups && !$this->hasArg('groups')) {
            $groups = $UserGroup->getId();
        }

        $url = rex_ynewsletter_exclusionlist::getUnsubscribeUrl($User[$UserGroup->getEMailField()], $groups, (int) $redirectToID, $redirectTo);

        $output = $this->getArg('output');
        switch ($output) {
            case 'url':
                $value = $url;
                break;
            case 'html':
            default:
                $value = '<a href="'.$url.'">{{ ynewsletter.unsubscribe }}</a>';
        }

        return self::quote($value);
    }
}
