<?php

class rex_ynewsletter_group extends \rex_yform_manager_dataset
{
    public function countUsers()
    {
        $Users = $this->getAllUsers();
        $Users = $this->filterExclusions($Users);
        return count($Users);
    }

    public function getExclusionsEMails()
    {
        $EMails = [];
        foreach (rex_ynewsletter_exclusionlist::getByGroupId($this->getId()) as $Entry) {
            $EMails[] = mb_strtolower($Entry->getValue('email'));
        }
        return $EMails;
    }

    public function getEMailField()
    {
        return $this->getValue('email');
    }

    public function filterExclusions($Users)
    {
        $ExclusionEMails = $this->getExclusionsEMails();
        $Group = $this;
        $Users = array_filter($Users, static function ($User) use ($ExclusionEMails, $Group) {
            if (in_array(mb_strtolower($User[$Group->getEMailField()]), $ExclusionEMails, true)) {
                return false;
            }
            return true;
        });
        return $Users;
    }

    public function getAllUsers()
    {
        $Users = [];
        $query = 'select * from `'.$this->getValue('table').'`';
        $filters = trim($this->getValue('filter'));
        if ('' != $filters) {
            $queryFilter = [];
            foreach (explode("\n", $filters) as $filter) {
                $queryFilter[] = '('.trim($filter).')';
            }
            $query .= ' where ' . implode(' and ', $queryFilter);
        }
        foreach (rex_sql::factory()->getArray($query) as $q) {
            $Users[$q['id']] = $q;
        }

        return $Users;
    }
}
