<?php

declare(strict_types=1);

return [
    'frontend' => [
        'rpflamm/lightroom-publish-service/api-resolver' => [
            'target' => \RPflamm\LightroomPublishService\Http\Middleware\ApiRequestResolver::class,
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
            'before' => [
                'typo3/cms-frontend/maintenance-mode',
                'typo3/cms-frontend/site',
                'typo3/cms-frontend/backend-user-authentication',
                'typo3/cms-frontend/authentication',
            ],
        ],
    ],
];
