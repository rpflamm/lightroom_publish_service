<?php

use RPflamm\LightroomPublishService\Controller\Backend\CollectionController;
use RPflamm\LightroomPublishService\Controller\Backend\PhotoController;
use RPflamm\LightroomPublishService\Controller\Backend\TokenController;

/**
 * Dedicated top-level menu section for the Lightroom Publish Service, with
 * Collection/API/Test-Connection as separate sub-modules.
 */
return [
    'lightroom_publish_service' => [
        'position' => ['after' => 'content'],
        'workspaces' => 'live',
        'iconIdentifier' => 'module-lightroom-publish-service',
        'labels' => [
            'title' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_mod.xlf:group.title',
            'description' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_mod.xlf:group.description',
            'shortDescription' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_mod.xlf:group.shortDescription',
        ],
    ],
    'lightroom_publish_service_collection' => [
        'parent' => 'lightroom_publish_service',
        'position' => ['before' => '*'],
        'access' => 'user',
        'workspaces' => 'live',
        'iconIdentifier' => 'module-lightroom-publish-service',
        'labels' => [
            'title' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_mod.xlf:collection.title',
            'description' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_mod.xlf:collection.description',
            'shortDescription' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_mod.xlf:collection.shortDescription',
        ],
        'path' => '/module/lightroom-publish-service/collection',
        'extensionName' => 'LightroomPublishService',
        'controllerActions' => [
            CollectionController::class => [
                'list',
            ],
            PhotoController::class => [
                'list',
            ],
        ],
    ],
    'lightroom_publish_service_api' => [
        'parent' => 'lightroom_publish_service',
        'position' => ['after' => 'lightroom_publish_service_collection'],
        'access' => 'user',
        'workspaces' => 'live',
        'iconIdentifier' => 'module-lightroom-publish-service',
        'labels' => [
            'title' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_mod.xlf:api.title',
            'description' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_mod.xlf:api.description',
            'shortDescription' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_mod.xlf:api.shortDescription',
        ],
        'path' => '/module/lightroom-publish-service/api',
        'extensionName' => 'LightroomPublishService',
        'controllerActions' => [
            TokenController::class => [
                'list', 'new', 'create', 'reveal', 'revoke',
            ],
        ],
    ],
    'lightroom_publish_service_test_connection' => [
        'parent' => 'lightroom_publish_service',
        'position' => ['after' => 'lightroom_publish_service_api'],
        'access' => 'user',
        'workspaces' => 'live',
        'iconIdentifier' => 'module-lightroom-publish-service',
        'labels' => [
            'title' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_mod.xlf:testConnection.title',
            'description' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_mod.xlf:testConnection.description',
            'shortDescription' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_mod.xlf:testConnection.shortDescription',
        ],
        'path' => '/module/lightroom-publish-service/test-connection',
        'extensionName' => 'LightroomPublishService',
        'controllerActions' => [
            PhotoController::class => [
                'connectionTest',
            ],
        ],
    ],
];
