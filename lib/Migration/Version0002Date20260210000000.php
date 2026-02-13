<?php

namespace OCA\NextDiary\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0002Date20260210000000 extends SimpleMigrationStep
{
    /** @var IDBConnection */
    private $db;

    /** @var array Saved entries from old table */
    private $savedEntries = [];

    public function __construct(IDBConnection $db)
    {
        $this->db = $db;
    }

    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('diary')) {
            return;
        }

        // Check if already migrated (new schema has created_at column)
        $table = $schema->getTable('diary');
        if ($table->hasColumn('created_at')) {
            $output->info('NextDiary: table already has new schema, skipping data migration');
            return;
        }

        // Save all existing entries from old table
        $qb = $this->db->getQueryBuilder();
        $qb->select('uid', 'entry_date', 'entry_content')
            ->from('diary')
            ->orderBy('uid', 'ASC')
            ->addOrderBy('entry_date', 'ASC');

        $result = $qb->executeQuery();
        $this->savedEntries = $result->fetchAll();
        $result->closeCursor();

        $output->info('NextDiary: saved ' . count($this->savedEntries) . ' entries for migration');

        // Drop old table via raw SQL to force CREATE TABLE instead of ALTER TABLE.
        // Doctrine's schema diff treats dropTable()+createTable() with same name as ALTER,
        // which fails when converting string ID to auto-increment integer.
        $this->db->executeStatement('DROP TABLE IF EXISTS `*PREFIX*diary`');
        $output->info('NextDiary: dropped old table');
    }

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('diary')) {
            $table = $schema->getTable('diary');
            // If already new schema, no changes needed
            if ($table->hasColumn('created_at')) {
                return $schema;
            }
            // Remove old table definition from cached schema object
            $schema->dropTable('diary');
        }

        // Create new table with auto-increment ID and timestamps
        $table = $schema->createTable('diary');
        $table->addColumn('id', 'integer', [
            'autoincrement' => true,
            'notnull' => true,
            'unsigned' => true,
        ]);
        $table->addColumn('uid', 'string', [
            'length' => 64,
            'notnull' => true,
        ]);
        $table->addColumn('entry_date', 'string', [
            'length' => 10,
            'notnull' => true,
        ]);
        $table->addColumn('entry_content', 'text', [
            'notnull' => false,
        ]);
        $table->addColumn('created_at', 'datetime', [
            'notnull' => false,
            'default' => null,
        ]);
        $table->addColumn('updated_at', 'datetime', [
            'notnull' => false,
            'default' => null,
        ]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['uid', 'entry_date'], 'diary_uid_date_idx');
        $table->addIndex(['uid', 'created_at'], 'diary_uid_created_idx');

        return $schema;
    }

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
        if (empty($this->savedEntries)) {
            return;
        }

        // Restore saved entries into new table
        $insert = $this->db->getQueryBuilder();
        $insert->insert('diary')
            ->values([
                'uid' => $insert->createParameter('uid'),
                'entry_date' => $insert->createParameter('entry_date'),
                'entry_content' => $insert->createParameter('entry_content'),
                'created_at' => $insert->createParameter('created_at'),
                'updated_at' => $insert->createParameter('updated_at'),
            ]);

        $count = 0;
        foreach ($this->savedEntries as $row) {
            $entryDate = $row['entry_date'] ?? '';

            // Skip entries with invalid or empty dates
            if ($entryDate === '' || $entryDate === '0000-00-00' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
                $output->warning('NextDiary: skipping entry with invalid date "' . $entryDate . '" for user ' . ($row['uid'] ?? 'unknown'));
                continue;
            }

            $timestamp = $entryDate . ' 12:00:00';
            $insert->setParameter('uid', $row['uid']);
            $insert->setParameter('entry_date', $entryDate);
            $insert->setParameter('entry_content', $row['entry_content']);
            $insert->setParameter('created_at', $timestamp);
            $insert->setParameter('updated_at', $timestamp);
            $insert->executeStatement();
            $count++;
        }

        $output->info('NextDiary: restored ' . $count . ' entries to new schema');
    }
}
