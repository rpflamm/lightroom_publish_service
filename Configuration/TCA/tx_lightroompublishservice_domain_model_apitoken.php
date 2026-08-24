<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_apitoken',
        'label' => 'name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'crdate DESC',
        'delete' => 'deleted',
        'hideTable' => true,
        'rootLevel' => 1,
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
        'iconfile' => 'EXT:lightroom_publish_service/Resources/Public/Icons/Extension.svg',
    ],
    'columns' => [
        'name' => [
            'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_apitoken.name',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'max' => 255,
                'required' => true,
                'eval' => 'trim',
            ],
        ],
        'selector' => [
            'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_apitoken.selector',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'max' => 32,
                'readOnly' => true,
            ],
        ],
        'verifier_hash' => [
            'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_apitoken.verifier_hash',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'max' => 128,
                'readOnly' => true,
            ],
        ],
        'last_used_at' => [
            'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_apitoken.last_used_at',
            'config' => [
                'type' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'last_used_ip' => [
            'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_apitoken.last_used_ip',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'max' => 45,
                'readOnly' => true,
            ],
        ],
        'revoked' => [
            'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_apitoken.revoked',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
            ],
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => 'name, selector, verifier_hash, last_used_at, last_used_ip, revoked',
        ],
    ],
];
