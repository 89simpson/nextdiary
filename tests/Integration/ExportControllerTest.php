<?php

namespace OCA\NextDiary\Tests\Integration\Controller;

use OCA\NextDiary\Controller\ExportController;
use OCA\NextDiary\Db\Entry;
use OCA\NextDiary\Db\EntryMapper;
use OCP\AppFramework\App;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use PHPUnit\Framework\TestCase;

/**
 * A single-entry export must not hand out another user's entry (403) and must
 * answer a non-existent id with 404 instead of an internal error.
 */
class ExportControllerTest extends TestCase
{
    private $userId = 'john';
    /** @var ExportController */
    private $controller;
    /** @var EntryMapper */
    private $mapper;
    /** @var Entry[] */
    private $inserted = [];

    public function setUp(): void
    {
        parent::setUp();
        $app = new App('nextdiary');
        $container = $app->getContainer();

        $container->registerService('UserId', function ($c) {
            return $this->userId;
        });

        $this->controller = $container->query(ExportController::class);
        $this->mapper = $container->query(EntryMapper::class);
    }

    public function tearDown(): void
    {
        foreach ($this->inserted as $entry) {
            $this->mapper->delete($entry);
        }
        $this->inserted = [];
        parent::tearDown();
    }

    public function testSingleOwnEntry()
    {
        $own = $this->insertEntry($this->userId, 'My own entry');

        $response = $this->controller->getMarkdown('single', $own->getId());

        $this->assertInstanceOf(DataDownloadResponse::class, $response);
        $this->assertEquals(Http::STATUS_OK, $response->getStatus());
        $this->assertStringContainsString('My own entry', $response->render());
    }

    public function testSingleForeignEntryIsForbidden()
    {
        $foreign = $this->insertEntry('dave', 'Dave\'s private entry');

        foreach (['getMarkdown', 'getPdf', 'getCsv'] as $method) {
            $response = $this->controller->$method('single', $foreign->getId());

            $this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus(), $method);
            $this->assertStringNotContainsString('private entry', $response->render(), $method);
        }
    }

    public function testSingleMissingEntryIsNotFound()
    {
        // An id that surely does not exist: past the newest entry
        $probe = $this->insertEntry($this->userId, 'Probe');
        $missingId = $probe->getId() + 1000;

        foreach (['getMarkdown', 'getPdf', 'getCsv'] as $method) {
            $response = $this->controller->$method('single', $missingId);

            $this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus(), $method);
        }
    }

    private function insertEntry(string $uid, string $content): Entry
    {
        $entry = new Entry();
        $entry->setUid($uid);
        $entry->setEntryDate('2022-03-04');
        $entry->setEntryContent($content);
        $entry->setCreatedAt(new \DateTime());
        $entry->setUpdatedAt(new \DateTime());
        $inserted = $this->mapper->insert($entry);
        $this->inserted[] = $inserted;

        return $inserted;
    }
}
