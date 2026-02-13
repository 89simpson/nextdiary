<?php

namespace OCA\NextDiary\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0006Date20260213000000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('diary_entry_files')) {
            $table = $schema->createTable('diary_entry_files');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('entry_id', 'integer', [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('uid', 'string', [
                'length' => 64,
                'notnull' => true,
            ]);
            $table->addColumn('file_path', 'string', [
                'length' => 512,
                'notnull' => true,
            ]);
            $table->addColumn('original_name', 'string', [
                'length' => 255,
                'notnull' => true,
            ]);
            $table->addColumn('mime_type', 'string', [
                'length' => 127,
                'notnull' => true,
            ]);
            $table->addColumn('size_bytes', 'bigint', [
                'notnull' => true,
                'unsigned' => true,
                'default' => 0,
            ]);
            $table->addColumn('uploaded_at', 'datetime', [
                'notnull' => false,
                'default' => null,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['entry_id'], 'diary_ef_entry_idx');
            $table->addIndex(['uid'], 'diary_ef_uid_idx');
        }

        return $schema;
    }
}
