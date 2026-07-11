<?php

namespace OCA\NextDiary\Tests\Unit\Controller;

use OCA\NextDiary\Controller\PageController;
use OCA\NextDiary\Db\Entry;
use OCA\NextDiary\Db\EntryMapper;
use OCA\NextDiary\Service\FileService;
use OCA\NextDiary\Service\MedicationService;
use OCA\NextDiary\Service\MoodService;
use OCA\NextDiary\Service\TagService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PageControllerTest extends TestCase
{
    /** @var PageController */
    private $controller;
    private $userId = 'john';
    /** @var EntryMapper|MockObject */
    private $mapper;
    /** @var TagService|MockObject */
    private $tagService;
    /** @var MoodService|MockObject */
    private $moodService;
    /** @var MedicationService|MockObject */
    private $medicationService;
    /** @var FileService|MockObject */
    private $fileService;

    public function setUp(): void
    {
        parent::setUp();

        $request = $this->createMock(IRequest::class);
        $this->mapper = $this->createMock(EntryMapper::class);
        $this->tagService = $this->createMock(TagService::class);
        $this->moodService = $this->createMock(MoodService::class);
        $this->medicationService = $this->createMock(MedicationService::class);
        $this->fileService = $this->createMock(FileService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->controller = new PageController(
            'nextdiary',
            $request,
            $this->userId,
            $this->mapper,
            $this->tagService,
            $this->moodService,
            $this->medicationService,
            $this->fileService,
            $logger
        );
    }

    public function testIndex()
    {
        $result = $this->controller->index();

        $this->assertInstanceOf(TemplateResponse::class, $result);
        $this->assertEquals('index', $result->getTemplateName());
    }

    public function testGetEntriesByDate()
    {
        $date = '2022-08-07';
        $entry = $this->createEntry(1, $date, 'Body text');
        $this->mapper->expects($this->once())
            ->method('findByDate')
            ->with($this->userId, $date)
            ->willReturn([$entry]);
        $this->stubEmptyRelations();

        $result = $this->controller->getEntriesByDate($date);

        $this->assertEquals(Http::STATUS_OK, $result->getStatus());
        $data = $result->getData();
        $this->assertCount(1, $data);
        $this->assertEquals(1, $data[0]['id']);
        $this->assertEquals($date, $data[0]['entryDate']);
        $this->assertEquals('Body text', $data[0]['entryContent']);
    }

    public function testGetEntryByIdReturnsEntry()
    {
        $entry = $this->createEntry(5, '2022-08-07', 'Body text');
        $this->mapper->expects($this->once())
            ->method('findById')
            ->with(5)
            ->willReturn($entry);
        $this->stubEmptyRelations();

        $result = $this->controller->getEntryById(5);

        $this->assertEquals(Http::STATUS_OK, $result->getStatus());
        $this->assertEquals(5, $result->getData()['id']);
    }

    public function testGetEntryByIdNotFound()
    {
        $this->mapper->expects($this->once())
            ->method('findById')
            ->with(99)
            ->willThrowException(new DoesNotExistException('missing'));

        $result = $this->controller->getEntryById(99);

        $this->assertEquals(Http::STATUS_NOT_FOUND, $result->getStatus());
        $this->assertEquals(['error' => 'Entry not found'], $result->getData());
    }

    public function testGetEntryByIdForbidden()
    {
        $entry = $this->createEntry(7, '2022-08-07', 'Body text');
        $entry->setUid('someone-else');
        $this->mapper->expects($this->once())
            ->method('findById')
            ->with(7)
            ->willReturn($entry);

        $result = $this->controller->getEntryById(7);

        $this->assertEquals(Http::STATUS_FORBIDDEN, $result->getStatus());
        $this->assertEquals(['error' => 'Forbidden'], $result->getData());
    }

    public function testCreateEntry()
    {
        $date = '2022-08-07';
        $entry = $this->createEntry(10, $date, 'Body text');
        $this->mapper->expects($this->once())
            ->method('insert')
            ->willReturn($entry);

        $result = $this->controller->createEntry($date, 'Body text');

        $this->assertEquals(Http::STATUS_CREATED, $result->getStatus());
        $this->assertSame($entry, $result->getData());
    }

    public function testDeleteEntry()
    {
        $entry = $this->createEntry(3, '2022-08-07', 'Body text');
        $this->mapper->expects($this->once())
            ->method('findById')
            ->with(3)
            ->willReturn($entry);
        $this->mapper->expects($this->once())
            ->method('delete')
            ->with($entry);

        $result = $this->controller->deleteEntry(3);

        $this->assertEquals(Http::STATUS_NO_CONTENT, $result->getStatus());
    }

    public function testGetEntryLegacy()
    {
        $date = '2022-08-07';
        $content = 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam';
        $entry = $this->createEntry(1, $date, $content);
        $this->mapper->expects($this->once())
            ->method('find')
            ->with($this->userId, $date)
            ->willReturn($entry);

        $result = $this->controller->getEntry($date);

        $this->assertEquals(Http::STATUS_OK, $result->getStatus());
        $this->assertSame($entry, $result->getData());
    }

    public function testGetEntryLegacyNotFound()
    {
        $date = '2022-08-07';
        $this->mapper->expects($this->once())
            ->method('find')
            ->with($this->userId, $date)
            ->willThrowException(new DoesNotExistException('Id not found'));

        $result = $this->controller->getEntry($date);

        $this->assertEquals(Http::STATUS_OK, $result->getStatus());
        $this->assertEquals(['isEmpty' => true], $result->getData());
    }

    public function testUpdateEntryLegacyUpdatesExisting()
    {
        $date = '2022-08-07';
        $content = 'Updated body';
        $entry = $this->createEntry(1, $date, 'Old body');
        $this->mapper->expects($this->once())
            ->method('find')
            ->with($this->userId, $date)
            ->willReturn($entry);
        $this->mapper->expects($this->once())
            ->method('update')
            ->with($entry)
            ->willReturn($entry);

        $result = $this->controller->updateEntry($date, $content);

        $this->assertEquals(Http::STATUS_OK, $result->getStatus());
        $this->assertSame($entry, $result->getData());
    }

    public function testUpdateEntryLegacyDeletesOnEmpty()
    {
        $date = '2022-08-07';
        $entry = $this->createEntry(1, $date, 'Body');
        $this->mapper->expects($this->once())
            ->method('findByDate')
            ->with($this->userId, $date)
            ->willReturn([$entry]);
        $this->mapper->expects($this->once())
            ->method('delete')
            ->with($entry);

        $result = $this->controller->updateEntry($date, '');

        $this->assertEquals(Http::STATUS_OK, $result->getStatus());
        $this->assertEquals(['isEmpty' => true], $result->getData());
    }

    /**
     * buildEntryResponse() reads related data through the relation services;
     * resolve them all to an empty set for entry-shape assertions.
     */
    private function stubEmptyRelations(): void
    {
        $this->moodService->method('decodeRatings')->willReturn(null);
        $this->tagService->method('getTagsForEntry')->willReturn([]);
        $this->moodService->method('getSymptomsForEntry')->willReturn([]);
        $this->medicationService->method('getMedicationsForEntry')->willReturn([]);
        $this->fileService->method('getFilesForEntry')->willReturn([]);
    }

    /**
     * Create an Entry element owned by the test user.
     */
    private function createEntry(int $id, string $date, string $content): Entry
    {
        $entry = new Entry();
        $entry->setId($id);
        $entry->setUid($this->userId);
        $entry->setEntryDate($date);
        $entry->setEntryContent($content);

        return $entry;
    }
}
