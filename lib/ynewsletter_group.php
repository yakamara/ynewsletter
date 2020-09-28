<?php

class rex_ynewsletter_group extends \rex_yform_manager_dataset
{
    public function countUsers()
    {
        $query = 'select count(id) as amount from `'.$this->table.'`';
        $group_filters = trim($this->filter);
        if ('' != $group_filters) {
            foreach (explode("\n", $group_filters) as $group_filter) {
                $filter[] = '('.trim($group_filter).')';
            }
            $query .= ' where ' . implode(' and ', $filter);
        }

        $amounts = rex_sql::factory()->getArray($query);
        return $amounts[0]['amount'];
    }
}
