<?php

class rex_ynewsletter_exclusionlist extends \rex_yform_manager_dataset
{
    public static function getByGroupId($groupId)
    {
        return self::query()
            ->where('group', $groupId)
            ->find();
    }
}
