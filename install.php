<?php

// Altlast aus YForm 3: select-Felder wurden durch choice ersetzt
rex_sql::factory()->setQuery(
    'delete from
                `' . rex::getTable('yform_field') . '`
           where
                table_name LIKE "rex_ynewsletter%" and
                type_name="select"',
);

// Legt Tabellen und YForm-Felder an bzw. gleicht sie ab (Felder werden über table_name + name erkannt)
$content = rex_file::get(rex_path::addon('ynewsletter', 'install/tablesets/ynewsletter_tables.json'));
rex_yform_manager_table_api::importTablesets($content);

// Spalten absichern, die bei einem Update aus älteren Versionen fehlen können.
// preheader und send_at gehören YForm; sie werden nur ergänzt, wenn sie fehlen, damit
// der Import den Spaltentyp weiter bestimmt. sending_started_at ist kein YForm-Feld
// (interne Versandsperre) und wird immer sichergestellt.
$table = rex_sql_table::get(rex::getTable('ynewsletter'));
if (!$table->hasColumn('preheader')) {
    $table->ensureColumn(new rex_sql_column('preheader', 'text', true));
}
if (!$table->hasColumn('send_at')) {
    $table->ensureColumn(new rex_sql_column('send_at', 'datetime', true));
}
$table
    ->ensureColumn(new rex_sql_column('sending_started_at', 'datetime', true), 'send_at')
    ->ensure();

rex_yform_manager_table::deleteCache();
