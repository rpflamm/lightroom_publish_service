<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_collection',
        'label' => 'name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'sortby' => 'sorting',
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
        'name' => [
            'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_collection.name',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'max' => 255,
                'required' => true,
                'eval' => 'trim',
            ],
        ],
        'collection_type' => [
            'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_collection.collection_type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_collection.collection_type.collection',
                        'value' => 'collection',
                    ],
                    [
                        'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_collection.collection_type.collection_set',
                        'value' => 'collection_set',
                    ],
                ],
                'default' => 'collection',
            ],
        ],
        'parent' => [
            'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_collection.parent',
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_lightroompublishservice_domain_model_collection',
                'size' => 1,
                'maxitems' => 1,
                'suggestOptions' => [
                    'default' => [
                        'searchWholePhrase' => true,
                    ],
                ],
            ],
        ],
        'description' => [
            'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_collection.description',
            'config' => [
                'type' => 'text',
                'rows' => 5,
                'cols' => 40,
            ],
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => 'name, collection_type, parent, description, --div--;LLL:EXT:core/Resources/Private/Language/locallang_tabs.xlf:access, hidden',
        ],
    ],
];
