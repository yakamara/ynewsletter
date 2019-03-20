<?php

class rex_yform_validate_ynewsletter_auth extends rex_yform_validate_abstract
{
    public function enterObject()
    {
        $query = [];
        $queryParams = [];

        $requestPairs = explode(',', $this->getElement('request'));
        foreach ($requestPairs as $requestPair) {
            $pair = explode('=', $requestPair);
            $label = trim($pair[0]);
            $value = trim(rex_request($pair[1], 'string', ''));
            $query[] = '`'.$label.'` = :'.$label;
            $queryParams[$label] = $value;
        }

        if ($this->getElement('condition') != '') {
            $pair = explode('=', $this->getElement('condition'));
            $label = trim($pair[0]);
            $value = trim($pair[1]);
            $query[] = '`'.$label.'` = :'.$label;
            $queryParams[$label] = $value;
        }

        $table = $this->getElement('table');

        $sql = rex_sql::factory();
        if ($this->params['debug']) {
            $sql->setDebug();
        }

        $sql->setQuery('SELECT * FROM `'.$table.'` WHERE '.implode(' AND ', $query), $queryParams);

        if ($sql->getRows() != 1) {
            $this->params['warning'][] = 1;
            $this->params['warning_messages'][] = rex_i18n::translate($this->getElement('message'));
        } else {
            $main_id = (int) $sql->getValue('id');
            $this->params['main_where'] = 'id='.$main_id;
            $this->params['main_id'] = $main_id;
            $this->params['main_table'] = $table;

            if ($this->getElement('fields') != '') {
                foreach (explode(',', $this->getElement('fields')) as $label) {
                    $this->params['value_pool']['email'][$label] = $sql->getValue($label);
                }
            }
        }

    }

    function getDescription()
    {
        return 'ynewsletter_auth -> Beispiel: validate|ynewsletter_auth|table|label1=request1,label2=request2|status=0|warning_message|Fields for E-Mail-Template';
    }

    public function getDefinitions()
    {
        return [
            'type' => 'validate',
            'name' => 'ynewsletter_auth',
            'values' => [
                'name' => ['type' => 'select_name', 'label' => rex_i18n::msg('ynewsletter_validate_auth_name')],
                'table' => ['type' => 'choice', 'label' => rex_i18n::msg('ynewsletter_validate_auth_table'), 'choices' => 'SELECT table_name AS id,  table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY name'],
                'request' => ['type' => 'text', 'label' => rex_i18n::msg('ynewsletter_validate_auth_request')],
                'condition' => ['type' => 'text', 'label' => rex_i18n::msg('ynewsletter_validate_auth_condition')],
                'message' => ['type' => 'text', 'label' => rex_i18n::msg('ynewsletter_validate_auth_message')],
                'fields' => ['type' => 'text', 'label' => rex_i18n::msg('ynewsletter_validate_auth_fields')],
            ],
            'description' => rex_i18n::msg('ynewsletter_validate_auth_description'),
            'famous' => false,
        ];
    }
}
