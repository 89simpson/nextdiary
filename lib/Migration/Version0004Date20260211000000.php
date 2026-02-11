<?php

namespace OCA\NextDiary\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0004Date20260211000000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Add entry_ratings JSON column to diary table
        $diaryTable = $schema->getTable('diary');
        if (!$diaryTable->hasColumn('entry_ratings')) {
            $diaryTable->addColumn('entry_ratings', 'text', [
                'notnull' => false,
                'default' => null,
            ]);
        }

        // Create diary_symptoms table
        if (!$schema->hasTable('diary_symptoms')) {
            $table = $schema->createTable('diary_symptoms');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('uid', 'string', [
                'length' => 64,
                'notnull' => true,
            ]);
            $table->addColumn('symptom_name', 'string', [
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
            $table->addUniqueIndex(['uid', 'symptom_name'], 'diary_sym_uid_name_uniq');
            $table->addIndex(['uid'], 'diary_sym_uid_idx');
        }

        // Create diary_entry_symptoms table
        if (!$schema->hasTable('diary_entry_symptoms')) {
            $table = $schema->createTable('diary_entry_symptoms');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('entry_id', 'integer', [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('symptom_id', 'integer', [
                'notnull' => true,
                'unsigned' => true,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['entry_id', 'symptom_id'], 'diary_es_entry_sym_uniq');
            $table->addIndex(['symptom_id', 'entry_id'], 'diary_es_sym_entry_idx');
            $table->addIndex(['entry_id'], 'diary_es_entry_idx');
        }

        return $schema;
    }
}
