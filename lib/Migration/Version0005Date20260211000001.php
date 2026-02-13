<?php

namespace OCA\NextDiary\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0005Date20260211000001 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Create diary_medications table
        if (!$schema->hasTable('diary_medications')) {
            $table = $schema->createTable('diary_medications');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('uid', 'string', [
                'length' => 64,
                'notnull' => true,
            ]);
            $table->addColumn('medication_name', 'string', [
                'length' => 100,
                'notnull' => true,
            ]);
            $table->addColumn('category', 'string', [
                'length' => 50,
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('created_at', 'datetime', [
                'notnull' => false,
                'default' => null,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['uid', 'medication_name'], 'diary_med_uid_name_uniq');
            $table->addIndex(['uid'], 'diary_med_uid_idx');
        }

        // Create diary_entry_meds table (short name to fit Nextcloud index name limits)
        if (!$schema->hasTable('diary_entry_meds')) {
            $table = $schema->createTable('diary_entry_meds');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('entry_id', 'integer', [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('medication_id', 'integer', [
                'notnull' => true,
                'unsigned' => true,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['entry_id', 'medication_id'], 'diary_em_entry_med_uniq');
            $table->addIndex(['medication_id', 'entry_id'], 'diary_em_med_entry_idx');
            $table->addIndex(['entry_id'], 'diary_em_entry_idx');
        }

        return $schema;
    }
}
