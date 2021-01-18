<?php

/**
 * REX_YNEWSLETTER_UNSUBSCRIBE[groups=1,3,2 redirectToID=3 output=url].
 */
class rex_var_ynewsletter_unsubscribe extends rex_var
{
    protected function getOutput()
    {
        if (!in_array($this->getContext(), ['ynewsletter_template'])) {
            return self::quote('');
        }

        $ContextData = $this->getContextData();
        $User = $ContextData['user'];

        /** @var rex_ynewsletter_group $UserGroup */
        $UserGroup = $ContextData['group'];

        $redirectToID = $this->getArg('redirectToID') ?? null;

        if (!$redirectToID) {
            return self::quote('redirectToID attribute is missing');
        }

        $groups = $this->getArg('groups') ?? null;
        if (!$groups && !$this->hasArg('groups')) {
            $groups = $UserGroup->getId();
        }

        $url = rex_ynewsletter_exclusionlist::getUnsubscribeUrl($User[$UserGroup->getEMailField()], $groups, $redirectToID);

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
