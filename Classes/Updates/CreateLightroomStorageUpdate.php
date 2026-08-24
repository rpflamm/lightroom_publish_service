<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Updates;

use RPflamm\LightroomPublishService\Service\StorageProvisioningService;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Provisions the dedicated "Lightroom Publish Service" FAL storage on install/upgrade.
 *
 * Implements TYPO3\CMS\Install\Updates\UpgradeWizardInterface (rather than the newer
 * TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface introduced in v14) since the former
 * still exists as a compatible alias in v14 and is the only variant available in v13,
 * keeping this wizard usable unchanged across both LTS versions.
 */
final class CreateLightroomStorageUpdate implements UpgradeWizardInterface
{
    public function __construct(
        private readonly StorageProvisioningService $storageProvisioningService,
    ) {}

    public function getTitle(): string
    {
        return 'Create the Lightroom Publish Service FAL storage';
    }

    public function getDescription(): string
    {
        return 'Creates a dedicated local file storage (fileadmin/lightroom_publish_service/) used exclusively '
            . 'by the Lightroom Publish Service API to store photos published from Adobe Lightroom Classic.';
    }

    public function updateNecessary(): bool
    {
        $configuredUid = (int)($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lightroom_publish_service']['storageUid'] ?? 0);

        return $configuredUid <= 0;
    }

    public function executeUpdate(): bool
    {
        $this->storageProvisioningService->getOrCreateStorage();

        return true;
    }

    public function getPrerequisites(): array
    {
        return [];
    }
}
