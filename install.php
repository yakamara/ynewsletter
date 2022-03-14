<?php

rex_sql::factory()->setQuery(
    'update
                `' . rex::getTable('yform_field') . '`
           set
                type_name = "choice",
                db_type = "text",
                expanded = 0,
                choices = options
           where
                table_name LIKE "rex_ynewsletter%" and
                type_name="select"'
);

$content = rex_file::get(rex_path::addon('ynewsletter', 'install/tablesets/ynewsletter_tables.json'));
rex_yform_manager_table_api::importTablesets($content);
