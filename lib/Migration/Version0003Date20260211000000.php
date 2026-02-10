<?php

namespace OCA\NextDiary\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0003Date20260211000000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Create diary_tags table
        if (!$schema->hasTable('diary_tags')) {
            $table = $schema->createTable('diary_tags');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('uid', 'string', [
                'length' => 64,
                'notnull' => true,
            ]);
            $table->addColumn('tag_name', 'string', [
                'length' => 50,
                'notnull' => true,
            ]);
            $table->addColumn('created_at', 'datetime', [
                'notnull' => false,
                'default' => null,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['uid', 'tag_name'], 'diary_tags_uid_name_uniq');
            $table->addIndex(['uid', 'created_at'], 'diary_tags_uid_created_idx');
        }

        // Create diary_entry_tags table
        if (!$schema->hasTable('diary_entry_tags')) {
            $table = $schema->createTable('diary_entry_tags');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('entry_id', 'integer', [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('tag_id', 'integer', [
                'notnull' => true,
                'unsigned' => true,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['entry_id', 'tag_id'], 'diary_et_entry_tag_uniq');
            $table->addIndex(['tag_id', 'entry_id'], 'diary_et_tag_entry_idx');
            $table->addIndex(['entry_id'], 'diary_et_entry_idx');
        }

        return $schema;
    }
}
