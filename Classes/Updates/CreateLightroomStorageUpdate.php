<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Updates;

/**
 * Provisions the dedicated "Lightroom Publish Service" FAL storage on install/upgrade.
 *
 * TYPO3 v14 moved the upgrade wizard contract from TYPO3\CMS\Install\Updates\UpgradeWizardInterface
 * to TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface. The old interface is deprecated since v14.0 and
 * will be removed in v15.0, while the new one does not exist in v13. Since the two classes are
 * declared inside a conditional block, PHP resolves "implements" when that branch actually
 * executes rather than at compile time, so only the interface used in the running TYPO3
 * version needs to exist: v14+ uses the new interface, v13 falls back to the old one.
 */
if (interface_exists(\TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface::class)) {
    final class CreateLightroomStorageUpdate implements \TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface
    {
        use CreateLightroomStorageUpdateTrait;
    }
} else {
    // TYPO3 v13 fallback; only reached when the v14 interface is missing.
    // @phpstan-ignore class.implementsDeprecatedInterface
    final class CreateLightroomStorageUpdate implements \TYPO3\CMS\Install\Updates\UpgradeWizardInterface
    {
        use CreateLightroomStorageUpdateTrait;
    }
}
