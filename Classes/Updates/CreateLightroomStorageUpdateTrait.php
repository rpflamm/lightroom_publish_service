<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Updates;

use RPflamm\LightroomPublishService\Service\StorageProvisioningService;

/**
 * Shared implementation for {@see CreateLightroomStorageUpdate}, which implements
 * whichever of the two version-specific UpgradeWizardInterface variants is available
 * (TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface in v14+, TYPO3\CMS\Install\Updates\UpgradeWizardInterface
 * in v13 -- the v13 interface was removed without a compatible alias in v14.3).
 */
trait CreateLightroomStorageUpdateTrait
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
        $configuredUid = (int) ($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lightroom_publish_service']['storageUid'] ?? 0);

        return $configuredUid <= 0;
    }

    public function executeUpdate(): bool
    {
        $this->storageProvisioningService->getOrCreateStorage();

        return true;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [];
    }
}
