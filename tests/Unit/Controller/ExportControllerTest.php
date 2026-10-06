<?php

namespace OCA\NextDiary\Tests\Unit\Controller;

use OCA\NextDiary\Controller\ExportController;
use OCA\NextDiary\Db\Entry;
use OCA\NextDiary\Db\EntryMapper;
use OCA\NextDiary\Service\ConversionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ExportControllerTest extends TestCase
{
    /** @var ExportController */
    private $controller;
    private $userId = 'john';
    /** @var EntryMapper|MockObject */
    private $mapper;
    /** @var ConversionService|MockObject */
    private $conversionService;

    public function setUp(): void
    {
        parent::setUp();

        $request = $this->createMock(IRequest::class);
        $this->mapper = $this->createMock(EntryMapper::class);
        $this->conversionService = $this->createMock(ConversionService::class);
        $this->conversionService->method('entriesToMarkdown')->willReturn('# markdown');
        $this->conversionService->method('entriesToPdf')->willReturn('%PDF-');
        $this->conversionService->method('entriesToCsv')->willReturn('csv');

        $this->controller = new ExportController(
            'nextdiary',
            $request,
            $this->userId,
            $this->mapper,
            $this->conversionService
        );
    }

    public function formatProvider(): array
    {
        return [
            'markdown' => ['getMarkdown'],
            'pdf' => ['getPdf'],
            'csv' => ['getCsv'],
        ];
    }

    /**
     * @dataProvider formatProvider
     */
    public function testSingleOwnEntryIsExported(string $method)
    {
        $entry = $this->createEntry(5, $this->userId, '2022-08-07');
        $this->mapper->expects($this->once())
            ->method('findById')
            ->with(5)
            ->willReturn($entry);

        $result = $this->controller->$method('single', 5);

        $this->assertInstanceOf(DataDownloadResponse::class, $result);
        $this->assertEquals(Http::STATUS_OK, $result->getStatus());
        $this->assertStringNotContainsString('error.txt', $result->getHeaders()['Content-Disposition']);
    }

    /**
     * @dataProvider formatProvider
     */
    public function testSingleForeignEntryIsForbidden(string $method)
    {
        $entry = $this->createEntry(7, 'someone-else', '2022-08-07');
        $this->mapper->expects($this->once())
            ->method('findById')
            ->with(7)
            ->willReturn($entry);
        $this->conversionService->expects($this->never())->method('entriesToMarkdown');
        $this->conversionService->expects($this->never())->method('entriesToPdf');
        $this->conversionService->expects($this->never())->method('entriesToCsv');

        $result = $this->controller->$method('single', 7);

        $this->assertInstanceOf(DataResponse::class, $result);
        $this->assertEquals(Http::STATUS_FORBIDDEN, $result->getStatus());
        $this->assertEquals(['error' => 'Forbidden'], $result->getData());
    }

    /**
     * @dataProvider formatProvider
     */
    public function testSingleMissingEntryIsNotFound(string $method)
    {
        $this->mapper->expects($this->once())
            ->method('findById')
            ->with(99)
            ->willThrowException(new DoesNotExistException('missing'));
        $this->conversionService->expects($this->never())->method('entriesToMarkdown');
        $this->conversionService->expects($this->never())->method('entriesToPdf');
        $this->conversionService->expects($this->never())->method('entriesToCsv');

        $result = $this->controller->$method('single', 99);

        $this->assertInstanceOf(DataResponse::class, $result);
        $this->assertEquals(Http::STATUS_NOT_FOUND, $result->getStatus());
        $this->assertEquals(['error' => 'Entry not found'], $result->getData());
    }

    /**
     * The other scopes only ever read the current user's entries.
     *
     * @dataProvider formatProvider
     */
    public function testOtherScopesAreScopedToUser(string $method)
    {
        $entry = $this->createEntry(1, $this->userId, '2022-08-07');
        $this->mapper->expects($this->never())->method('findById');
        $this->mapper->expects($this->once())
            ->method('findByDate')
            ->with($this->userId, '2022-08-07')
            ->willReturn([$entry]);
        $this->mapper->expects($this->once())
            ->method('findByDateRange')
            ->with($this->userId, '2022-08-01', '2022-08-31')
            ->willReturn([$entry]);
        $this->mapper->expects($this->once())
            ->method('findAll')
            ->with($this->userId)
            ->willReturn([$entry]);

        $responses = [
            $this->controller->$method('day', null, '2022-08-07'),
            $this->controller->$method('range', null, null, '2022-08-01', '2022-08-31'),
            $this->controller->$method('all'),
        ];

        foreach ($responses as $result) {
            $this->assertInstanceOf(DataDownloadResponse::class, $result);
            $this->assertEquals(Http::STATUS_OK, $result->getStatus());
        }
    }

    public function testSingleWithoutEntryIdIsRejected()
    {
        $this->mapper->expects($this->never())->method('findById');

        $result = $this->controller->getMarkdown('single');

        $this->assertInstanceOf(DataDownloadResponse::class, $result);
        $this->assertStringContainsString('error.txt', $result->getHeaders()['Content-Disposition']);
    }

    private function createEntry(int $id, string $uid, string $date): Entry
    {
        $entry = new Entry();
        $entry->setId($id);
        $entry->setUid($uid);
        $entry->setEntryDate($date);
        $entry->setEntryContent('Body text');

        return $entry;
    }
}
