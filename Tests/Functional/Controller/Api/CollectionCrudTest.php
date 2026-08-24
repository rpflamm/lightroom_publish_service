<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Tests\Functional\Controller\Api;

use PHPUnit\Framework\Attributes\Test;
use RPflamm\LightroomPublishService\Tests\Functional\AbstractApiTestCase;

final class CollectionCrudTest extends AbstractApiTestCase
{
    #[Test]
    public function createsAndListsCollections(): void
    {
        $this->createCollection('Portugal 2026');

        $response = $this->executeFrontendSubRequest(
            $this->authenticated(self::API_BASE . '/collections'),
        );
        $data = json_decode((string)$response->getBody(), true)['data'];

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $data);
        self::assertSame('Portugal 2026', $data[0]['name']);
    }

    #[Test]
    public function supportsNestedCollectionSets(): void
    {
        $parentResponse = $this->executeFrontendSubRequest(
            $this->jsonRequest('POST', self::API_BASE . '/collections', ['name' => 'Camino 2026', 'type' => 'collection_set']),
        );
        $parentId = json_decode((string)$parentResponse->getBody(), true)['data']['id'];

        $childResponse = $this->executeFrontendSubRequest(
            $this->jsonRequest('POST', self::API_BASE . '/collections', ['name' => 'Day 1', 'parent' => $parentId]),
        );
        $childData = json_decode((string)$childResponse->getBody(), true)['data'];

        self::assertSame(201, $childResponse->getStatusCode());
        self::assertSame($parentId, $childData['parent']);
    }

    #[Test]
    public function rejectsPlainCollectionAsParent(): void
    {
        $collectionId = $this->createCollection('Not A Set');

        $response = $this->executeFrontendSubRequest(
            $this->jsonRequest('POST', self::API_BASE . '/collections', ['name' => 'Day 1', 'parent' => $collectionId]),
        );
        $body = json_decode((string)$response->getBody(), true);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('invalid_parent_type', $body['error']['code']);
    }

    #[Test]
    public function renamesCollectionViaPatch(): void
    {
        $collectionId = $this->createCollection('Old Name');

        $response = $this->executeFrontendSubRequest(
            $this->jsonRequest('PATCH', self::API_BASE . '/collections/' . $collectionId, ['name' => 'New Name']),
        );
        $data = json_decode((string)$response->getBody(), true)['data'];

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('New Name', $data['name']);
    }

    #[Test]
    public function rejectsDuplicateNameUnderSameParent(): void
    {
        $this->createCollection('Duplicate');

        $response = $this->executeFrontendSubRequest(
            $this->jsonRequest('POST', self::API_BASE . '/collections', ['name' => 'Duplicate']),
        );
        $body = json_decode((string)$response->getBody(), true);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('duplicate_name', $body['error']['code']);
    }

    #[Test]
    public function deletingNonEmptyCollectionWithoutCascadeReturns409(): void
    {
        $collectionId = $this->createCollection();
        $this->uploadPhoto($collectionId);

        $response = $this->executeFrontendSubRequest(
            $this->authenticated(self::API_BASE . '/collections/' . $collectionId)->withMethod('DELETE'),
        );
        $body = json_decode((string)$response->getBody(), true);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('not_empty', $body['error']['code']);
    }

    #[Test]
    public function cascadeDeleteRemovesPhotosAndFiles(): void
    {
        $collectionId = $this->createCollection();
        $photo = $this->uploadPhoto($collectionId);

        $response = $this->executeFrontendSubRequest(
            $this->authenticated(self::API_BASE . '/collections/' . $collectionId . '?cascade=1')->withMethod('DELETE'),
        );
        self::assertSame(200, $response->getStatusCode());

        $showResponse = $this->executeFrontendSubRequest(
            $this->authenticated(self::API_BASE . '/photos/' . $photo['id']),
        );
        self::assertSame(404, $showResponse->getStatusCode());
    }
}
