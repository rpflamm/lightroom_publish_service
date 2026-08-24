<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Two fields Lightroom exposes that have no equivalent column in the
// standard sys_file_metadata schema (populated by EXT:filemetadata):
// GPS altitude and the color label (red/yellow/green/blue/purple).
$GLOBALS['TCA']['sys_file_metadata']['columns']['tx_lightroompublishservice_gps_altitude'] = [
    'exclude' => true,
    'l10n_mode' => 'exclude',
    'l10n_display' => 'defaultAsReadonly',
    'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:sys_file_metadata.gps_altitude',
    'config' => [
        'type' => 'number',
        'format' => 'decimal',
        'size' => 10,
        'nullable' => true,
    ],
];

$GLOBALS['TCA']['sys_file_metadata']['columns']['tx_lightroompublishservice_color_label'] = [
    'exclude' => true,
    'l10n_mode' => 'exclude',
    'l10n_display' => 'defaultAsReadonly',
    'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:sys_file_metadata.color_label',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'items' => [
            ['label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_photo.color_label.none', 'value' => ''],
            ['label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_photo.color_label.red', 'value' => 'red'],
            ['label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_photo.color_label.yellow', 'value' => 'yellow'],
            ['label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_photo.color_label.green', 'value' => 'green'],
            ['label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_photo.color_label.blue', 'value' => 'blue'],
            ['label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:tx_lightroompublishservice_domain_model_photo.color_label.purple', 'value' => 'purple'],
        ],
    ],
];

$GLOBALS['TCA']['sys_file_metadata']['palettes']['lightroomPublishService'] = [
    'label' => 'LLL:EXT:lightroom_publish_service/Resources/Private/Language/locallang_db.xlf:sys_file_metadata.palette.lightroom',
    'showitem' => 'tx_lightroompublishservice_gps_altitude, --linebreak--, tx_lightroompublishservice_color_label',
];

// Only relevant for images (this extension only publishes JPEG photos in v1).
ExtensionManagementUtility::addToAllTCAtypes(
    'sys_file_metadata',
    '--palette--;;lightroomPublishService',
    (string)FileType::IMAGE->value,
    'after:--palette--;;30',
);
