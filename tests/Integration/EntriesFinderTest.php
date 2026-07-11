<?php

namespace OCA\NextDiary\Tests\Integration\Controller;

use OCA\NextDiary\Db\Entry;
use OCA\NextDiary\Db\EntryMapper;
use OCP\AppFramework\App;
use PHPUnit\Framework\TestCase;

class EntriesFinderTest extends TestCase
{
    private $userId = 'john';
    /** @var EntryMapper */
    private $mapper;

    public function setUp(): void
    {
        parent::setUp();
        $app = new App('nextdiary');
        $container = $app->getContainer();

        $container->registerService('UserId', function ($c) {
            return $this->userId;
        });

        $this->mapper = $container->query(EntryMapper::class);
    }

    public function testGetExistingEntry()
    {
        $date = '2022-01-01';
        $content = 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam';
        $entry = $this->makeEntry($this->userId, $date, $content);
        $inserted = $this->mapper->insert($entry);

        $content2 = 'Same day, different user';
        $uid2 = 'dave';
        $entry2 = $this->makeEntry($uid2, $date, $content2);
        $inserted2 = $this->mapper->insert($entry2);

        $found = $this->mapper->find($this->userId, $date);
        $data = $found->jsonSerialize();
        $this->assertEquals($date, $data['entryDate']);
        $this->assertEquals($content, $data['entryContent']);
        $this->assertEquals($this->userId, $data['uid']);
        $this->assertEquals($inserted->getId(), $data['id']);

        $found2 = $this->mapper->find($uid2, $date);
        $data2 = $found2->jsonSerialize();
        $this->assertEquals($date, $data2['entryDate']);
        $this->assertEquals($content2, $data2['entryContent']);
        $this->assertEquals($uid2, $data2['uid']);
        $this->assertEquals($inserted2->getId(), $data2['id']);

        $this->mapper->delete($inserted);
        $this->mapper->delete($inserted2);
    }

    public function testGetExistingEntries()
    {
        $content = 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam';
        $entry = $this->makeEntry($this->userId, '2022-02-01', $content);
        $this->mapper->insert($entry);

        $content2 = 'Same day, different user';
        $entry2 = $this->makeEntry('dave', '2022-02-01', $content2);
        $this->mapper->insert($entry2);

        $content3 = 'Same user, different day, earlier';
        $entry3 = $this->makeEntry($this->userId, '2022-01-02', $content3);
        $this->mapper->insert($entry3);

        $content4 = 'Same user, different day, later';
        $entry4 = $this->makeEntry($this->userId, '2022-02-02', $content4);
        $this->mapper->insert($entry4);

        // findAll() orders by entry_date ascending.
        $insertedEntries = $this->mapper->findAll($this->userId);
        $this->assertCount(3, $insertedEntries);
        $this->assertEquals('2022-01-02', $insertedEntries[0]->getEntryDate());
        $this->assertEquals($content3, $insertedEntries[0]->getEntryContent());
        $this->assertEquals('2022-02-01', $insertedEntries[1]->getEntryDate());
        $this->assertEquals($content, $insertedEntries[1]->getEntryContent());
        $this->assertEquals('2022-02-02', $insertedEntries[2]->getEntryDate());
        $this->assertEquals($content4, $insertedEntries[2]->getEntryContent());

        // findLast() orders by created_at descending.
        $lastInsert = $this->mapper->findLast($this->userId, 1);
        $this->assertCount(1, $lastInsert);
        $this->assertEquals('2022-02-02', $lastInsert[0]->getEntryDate());

        $lastThreeInserts = $this->mapper->findLast($this->userId, 3);
        $this->assertCount(3, $lastThreeInserts);
        $this->assertEquals('2022-02-02', $lastThreeInserts[0]->getEntryDate());
        $this->assertEquals('2022-02-01', $lastThreeInserts[1]->getEntryDate());
        $this->assertEquals('2022-01-02', $lastThreeInserts[2]->getEntryDate());

        $this->mapper->delete($entry);
        $this->mapper->delete($entry2);
        $this->mapper->delete($entry3);
        $this->mapper->delete($entry4);
    }

    /**
     * Build an Entry for the current schema (id is auto-incremented, so it is not set here).
     * created_at is aligned with entry_date so created_at ordering matches date ordering.
     */
    private function makeEntry(string $uid, string $date, string $content): Entry
    {
        $entry = new Entry();
        $entry->setUid($uid);
        $entry->setEntryDate($date);
        $entry->setEntryContent($content);
        $entry->setCreatedAt(new \DateTime($date . ' 12:00:00'));
        $entry->setUpdatedAt(new \DateTime($date . ' 12:00:00'));

        return $entry;
    }
}
