<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_photo',
        'label' => 'content_hash',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'crdate DESC',
        'delete' => 'deleted',
        'hideTable' => true,
        'rootLevel' => 1,
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:lightroom_publish_service/Resources/Public/Icons/Extension.svg',
    ],
    'columns' => [
        'collection' => [
            'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_photo.collection',
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_lightroompublishservice_domain_model_collection',
                'size' => 1,
                'maxitems' => 1,
                'minitems' => 1,
            ],
        ],
        'file' => [
            'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_photo.file',
            'config' => [
                'type' => 'file',
                'allowed' => 'common-image-types',
                'maxitems' => 1,
                'relationship' => 'manyToOne',
            ],
        ],
        'content_hash' => [
            'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_photo.content_hash',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'max' => 64,
                'readOnly' => true,
            ],
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => 'collection, file, content_hash,
                --div--;LLL:EXT:core/Resources/Private/Language/locallang_tabs.xlf:access, hidden',
        ],
    ],
];
