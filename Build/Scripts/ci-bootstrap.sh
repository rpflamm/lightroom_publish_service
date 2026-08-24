#!/usr/bin/env bash
#
# Scaffolds a minimal TYPO3 project root around this checked-out extension so
# that Build/UnitTests.xml / Build/FunctionalTests.xml resolve their bootstrap
# path (../../../vendor/typo3/testing-framework/...) exactly as they do when
# this extension is consumed via a composer path repository from the parent
# "lightroom" monorepo (packages/lightroom_publish_service).
#
# Usage:
#   Build/Scripts/ci-bootstrap.sh <ci-root-dir> [typo3-constraint]
#
# Example:
#   Build/Scripts/ci-bootstrap.sh /tmp/ci-root "^14.3"
#   cd /tmp/ci-root
#   vendor/bin/phpunit -c packages/lightroom_publish_service/Build/UnitTests.xml
#
set -euo pipefail

CI_ROOT="${1:?usage: ci-bootstrap.sh <ci-root-dir> [typo3-constraint]}"
TYPO3_CONSTRAINT="${2:-^14.3}"
EXT_KEY="lightroom_publish_service"
EXT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"


# NOTE: this must be a real copy, not a symlink. Build/{Unit,Functional}Tests.xml
# resolve their bootstrap path via literal "../../../vendor" traversal; POSIX
# path resolution follows a symlink to its real target *before* applying "..",
# so a symlinked extension dir would make ".." climb from the checkout's real
# location instead of from CI_ROOT, breaking the three-levels-up lookup.
mkdir -p "${CI_ROOT}/packages/${EXT_KEY}"
cp -a "${EXT_DIR}/." "${CI_ROOT}/packages/${EXT_KEY}/"
rm -rf "${CI_ROOT}/packages/${EXT_KEY}/.git" "${CI_ROOT}/packages/${EXT_KEY}/vendor"

cat > "${CI_ROOT}/composer.json" <<JSON
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/${EXT_KEY}",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "rpflamm/lightroom-publish-service": "@dev",
        "typo3/cms-core": "${TYPO3_CONSTRAINT}",
        "typo3/cms-backend": "${TYPO3_CONSTRAINT}",
        "typo3/cms-extbase": "${TYPO3_CONSTRAINT}",
        "typo3/cms-filemetadata": "${TYPO3_CONSTRAINT}",
        "typo3/cms-fluid": "${TYPO3_CONSTRAINT}",
        "typo3/cms-fluid-styled-content": "${TYPO3_CONSTRAINT}"
    },
    "require-dev": {
        "typo3/testing-framework": "^9.1 || ^10.0"
    },
    "autoload-dev": {
        "psr-4": {
            "RPflamm\\\\LightroomPublishService\\\\Tests\\\\": "packages/${EXT_KEY}/Tests/"
        }
    },
    "config": {
        "allow-plugins": {
            "typo3/class-alias-loader": true,
            "typo3/cms-composer-installers": true
        },
        "sort-packages": true
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
JSON

cd "${CI_ROOT}"
composer update --no-progress --no-interaction --prefer-dist
