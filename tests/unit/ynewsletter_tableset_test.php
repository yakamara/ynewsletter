<?php

use PHPUnit\Framework\TestCase;

/**
 * YForm ignoriert Felder, deren Klasse fehlt, beim Abgleich in setTableField(). Ein solches Feld
 * im Tableset wird deshalb bei jedem Install/Update erneut eingefügt.
 *
 * @internal
 */
final class rex_ynewsletter_tableset_test extends TestCase
{
    public function testEveryFieldHasAYformClass(): void
    {
        $tablesets = json_decode((string) file_get_contents(__DIR__ . '/../../install/tablesets/ynewsletter_tables.json'), true);
        self::assertIsArray($tablesets);

        $missing = [];
        foreach ($tablesets as $tableName => $tableset) {
            foreach ($tableset['fields'] as $field) {
                $class = 'rex_yform_' . $field['type_id'] . '_' . $field['type_name'];
                if (!class_exists($class)) {
                    $missing[] = $tableName . ': ' . $field['type_id'] . ' ' . $field['type_name'] . ' ' . $field['name'];
                }
            }
        }

        self::assertSame([], $missing);
    }
}
