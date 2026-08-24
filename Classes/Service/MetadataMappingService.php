<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Service;

use RPflamm\LightroomPublishService\Domain\Model\Photo;
use RPflamm\LightroomPublishService\Exception\ValidationException;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;

/**
 * Bidirectional mapping between the JSON API's "metadata" payload shape and
 * the sys_file_metadata columns of the photo's underlying FAL file.
 */
final class MetadataMappingService
{
    private const VALID_COLOR_LABELS = ['', 'red', 'yellow', 'green', 'blue', 'purple'];

    public function __construct(
        private readonly MetaDataRepository $metaDataRepository,
    ) {}

    /**
     * Validates a (possibly partial, e.g. for PATCH) JSON metadata payload and
     * writes it into the file's sys_file_metadata row, creating that row on
     * demand. Only keys present in $metadata are written, so callers can
     * apply this directly for a PATCH without clobbering untouched columns.
     *
     * @param array<string, mixed> $metadata
     * @throws ValidationException
     */
    public function applyToFile(int $fileUid, array $metadata): void
    {
        $this->applyRow($fileUid, $this->toMetadataRow($metadata));
    }

    /**
     * @param array<string, mixed> $row a row previously returned by toMetadataRow()
     */
    public function applyRow(int $fileUid, array $row): void
    {
        if ($row === []) {
            return;
        }

        $existing = $this->metaDataRepository->findByFileUid($fileUid);
        if ($existing === []) {
            $this->metaDataRepository->createMetaDataRecord($fileUid, $row);
        } else {
            $this->metaDataRepository->update($fileUid, $row, $existing);
        }
    }

    /**
     * Validates a (possibly partial) JSON metadata payload and maps it to
     * sys_file_metadata column values, without writing anything. Useful to
     * validate a payload before an upload has produced a file uid to write to.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function toMetadataRow(array $metadata): array
    {
        $row = [];

        if (array_key_exists('title', $metadata)) {
            $row['title'] = (string)$metadata['title'];
        }
        if (array_key_exists('caption', $metadata)) {
            $row['caption'] = (string)$metadata['caption'];
        }
        if (array_key_exists('keywords', $metadata)) {
            if (!is_array($metadata['keywords'])) {
                throw new ValidationException('"keywords" must be an array of strings.', 'invalid_keywords');
            }
            $row['keywords'] = implode(', ', array_values(array_map('strval', $metadata['keywords'])));
        }
        if (array_key_exists('gps', $metadata)) {
            $gps = $metadata['gps'];
            if ($gps === null) {
                $row['latitude'] = 0.0;
                $row['longitude'] = 0.0;
            } elseif (is_array($gps) && isset($gps['latitude'], $gps['longitude'])) {
                $row['latitude'] = $this->validateRange((float)$gps['latitude'], -90, 90, 'gps.latitude');
                $row['longitude'] = $this->validateRange((float)$gps['longitude'], -180, 180, 'gps.longitude');
            } else {
                throw new ValidationException('"gps" must be null or an object with "latitude" and "longitude".', 'invalid_gps');
            }
        }
        if (array_key_exists('gpsAltitude', $metadata)) {
            $row['tx_lightroompublishservice_gps_altitude'] = $metadata['gpsAltitude'] !== null ? (float)$metadata['gpsAltitude'] : null;
        }
        if (array_key_exists('rating', $metadata)) {
            $row['ranking'] = $metadata['rating'] !== null
                ? (int)$this->validateRange((float)$metadata['rating'], 0, 5, 'rating')
                : 0;
        }
        if (array_key_exists('colorLabel', $metadata)) {
            $colorLabel = (string)($metadata['colorLabel'] ?? '');
            if (!in_array($colorLabel, self::VALID_COLOR_LABELS, true)) {
                throw new ValidationException(
                    sprintf('"colorLabel" must be one of: %s.', implode(', ', self::VALID_COLOR_LABELS)),
                    'invalid_color_label',
                );
            }
            $row['tx_lightroompublishservice_color_label'] = $colorLabel;
        }
        if (array_key_exists('creator', $metadata)) {
            $row['creator'] = (string)$metadata['creator'];
        }
        if (array_key_exists('copyright', $metadata)) {
            $row['copyright'] = (string)$metadata['copyright'];
        }
        if (array_key_exists('dateCreated', $metadata)) {
            $row['content_creation_date'] = $this->parseDate($metadata['dateCreated']) ?? 0;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(Photo $photo, ?string $publicUrl = null): array
    {
        $metadata = $photo->fileUid !== null ? $this->metaDataRepository->findByFileUid($photo->fileUid) : [];

        $latitude = (float)($metadata['latitude'] ?? 0.0);
        $longitude = (float)($metadata['longitude'] ?? 0.0);
        $gpsAltitude = $metadata['tx_lightroompublishservice_gps_altitude'] ?? null;
        $colorLabel = (string)($metadata['tx_lightroompublishservice_color_label'] ?? '');
        $keywords = trim((string)($metadata['keywords'] ?? ''));
        $contentCreationDate = (int)($metadata['content_creation_date'] ?? 0);

        return [
            'id' => (string)$photo->uid,
            'collection' => (string)$photo->collection,
            'url' => $publicUrl,
            'metadataUid' => isset($metadata['uid']) ? (int)$metadata['uid'] : null,
            'title' => (string)($metadata['title'] ?? ''),
            'caption' => (string)($metadata['caption'] ?? ''),
            'keywords' => $keywords !== '' ? array_map('trim', explode(',', $keywords)) : [],
            'gps' => $latitude !== 0.0 || $longitude !== 0.0
                ? ['latitude' => $latitude, 'longitude' => $longitude]
                : null,
            'gpsAltitude' => $gpsAltitude !== null ? (float)$gpsAltitude : null,
            'rating' => (int)($metadata['ranking'] ?? 0),
            'colorLabel' => $colorLabel !== '' ? $colorLabel : null,
            'creator' => (string)($metadata['creator'] ?? ''),
            'copyright' => (string)($metadata['copyright'] ?? ''),
            'dateCreated' => $contentCreationDate !== 0
                ? (new \DateTimeImmutable('@' . $contentCreationDate))->format(\DateTimeInterface::ATOM)
                : null,
            'contentHash' => $photo->contentHash !== '' ? 'sha256:' . $photo->contentHash : null,
        ];
    }

    private function validateRange(float $value, float $min, float $max, string $field): float
    {
        if ($value < $min || $value > $max) {
            throw new ValidationException(sprintf('"%s" must be between %s and %s.', $field, $min, $max), 'out_of_range');
        }

        return $value;
    }

    private function parseDate(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable((string)$value))->getTimestamp();
        } catch (\Exception) {
            throw new ValidationException('"dateCreated" must be a valid ISO-8601 date string.', 'invalid_date');
        }
    }
}
