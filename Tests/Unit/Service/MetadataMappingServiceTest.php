<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use RPflamm\LightroomPublishService\Domain\Model\Photo;
use RPflamm\LightroomPublishService\Exception\ValidationException;
use RPflamm\LightroomPublishService\Service\MetadataMappingService;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class MetadataMappingServiceTest extends UnitTestCase
{
    private MetaDataRepository&\PHPUnit\Framework\MockObject\MockObject $metaDataRepository;
    private MetadataMappingService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metaDataRepository = $this->createMock(MetaDataRepository::class);
        $this->subject = new MetadataMappingService($this->metaDataRepository);
    }

    #[Test]
    public function toMetadataRowMapsAllSupportedFields(): void
    {
        $row = $this->subject->toMetadataRow([
            'title' => 'Cathedral',
            'caption' => 'Santiago',
            'keywords' => ['camino', 'spain'],
            'gps' => ['latitude' => 42.88, 'longitude' => -8.54],
            'gpsAltitude' => 260.4,
            'rating' => 5,
            'colorLabel' => 'green',
            'creator' => 'R. Pflamm',
            'copyright' => '(c) 2026',
            'dateCreated' => '2026-06-12T18:42:00Z',
        ]);

        self::assertSame('Cathedral', $row['title']);
        self::assertSame('Santiago', $row['caption']);
        self::assertSame('camino, spain', $row['keywords']);
        self::assertSame(42.88, $row['latitude']);
        self::assertSame(-8.54, $row['longitude']);
        self::assertSame(260.4, $row['tx_lightroompublishservice_gps_altitude']);
        self::assertSame(5, $row['ranking']);
        self::assertSame('green', $row['tx_lightroompublishservice_color_label']);
        self::assertSame('R. Pflamm', $row['creator']);
        self::assertSame('(c) 2026', $row['copyright']);
        self::assertIsInt($row['content_creation_date']);
    }

    #[Test]
    public function toMetadataRowOnlyIncludesProvidedKeysForPartialUpdates(): void
    {
        $row = $this->subject->toMetadataRow(['title' => 'Only title changed']);

        self::assertSame(['title' => 'Only title changed'], $row);
    }

    #[Test]
    public function toMetadataRowRejectsRatingOutOfRange(): void
    {
        $this->expectException(ValidationException::class);
        $this->subject->toMetadataRow(['rating' => 6]);
    }

    #[Test]
    public function toMetadataRowRejectsInvalidColorLabel(): void
    {
        $this->expectException(ValidationException::class);
        $this->subject->toMetadataRow(['colorLabel' => 'orange']);
    }

    #[Test]
    public function toMetadataRowRejectsNonArrayKeywords(): void
    {
        $this->expectException(ValidationException::class);
        $this->subject->toMetadataRow(['keywords' => 'not-an-array']);
    }

    #[Test]
    public function toMetadataRowRejectsInvalidDate(): void
    {
        $this->expectException(ValidationException::class);
        $this->subject->toMetadataRow(['dateCreated' => 'not-a-date']);
    }

    #[Test]
    public function toMetadataRowClearsGpsToZeroWhenExplicitlyNull(): void
    {
        $row = $this->subject->toMetadataRow(['gps' => null]);

        self::assertSame(0.0, $row['latitude']);
        self::assertSame(0.0, $row['longitude']);
    }

    #[Test]
    public function toApiArrayBuildsTheExpectedResponseShape(): void
    {
        $this->metaDataRepository->method('findByFileUid')->with(10)->willReturn([
            'uid' => 42,
            'title' => 'Cathedral',
            'caption' => 'Santiago',
            'keywords' => 'camino, spain',
            'latitude' => 42.88,
            'longitude' => -8.54,
            'tx_lightroompublishservice_gps_altitude' => 260.4,
            'ranking' => 5,
            'tx_lightroompublishservice_color_label' => 'green',
            'creator' => 'R. Pflamm',
            'copyright' => '(c) 2026',
            'content_creation_date' => 1781289720,
        ]);

        $photo = new Photo(uid: 5, collection: 2, fileUid: 10, contentHash: 'abc123', tstamp: 0);

        $result = $this->subject->toApiArray($photo, 'https://example.com/photo.jpg');

        self::assertSame('5', $result['id']);
        self::assertSame('2', $result['collection']);
        self::assertSame('https://example.com/photo.jpg', $result['url']);
        self::assertSame(['camino', 'spain'], $result['keywords']);
        self::assertSame(['latitude' => 42.88, 'longitude' => -8.54], $result['gps']);
        self::assertSame('green', $result['colorLabel']);
        self::assertSame('sha256:abc123', $result['contentHash']);
        self::assertSame(42, $result['metadataUid']);
    }

    #[Test]
    public function toApiArrayReturnsNullGpsWhenNoFileIsLinked(): void
    {
        $this->metaDataRepository->expects(self::never())->method('findByFileUid');

        $photo = new Photo(uid: 1, collection: 1, fileUid: null, contentHash: '', tstamp: 0);

        $result = $this->subject->toApiArray($photo);

        self::assertNull($result['gps']);
        self::assertNull($result['dateCreated']);
        self::assertNull($result['contentHash']);
        self::assertNull($result['colorLabel']);
        self::assertNull($result['metadataUid']);
    }
}
