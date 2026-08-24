<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Updates;

/**
 * Provisions the dedicated "Lightroom Publish Service" FAL storage on install/upgrade.
 *
 * TYPO3 v14 moved the upgrade wizard contract from TYPO3\CMS\Install\Updates\UpgradeWizardInterface
 * to TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface and removed the old interface outright (no
 * compatible alias), so a single class can't implement both. Since the two interfaces are
 * declared inside a conditional block, PHP resolves "implements" when that branch actually
 * executes rather than at compile time, so only the interface available in the running TYPO3
 * version needs to exist.
 */
if (interface_exists(\TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface::class)) {
    final class CreateLightroomStorageUpdate implements \TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface
    {
        use CreateLightroomStorageUpdateTrait;
    }
} else {
    final class CreateLightroomStorageUpdate implements \TYPO3\CMS\Install\Updates\UpgradeWizardInterface
    {
        use CreateLightroomStorageUpdateTrait;
    }
}
