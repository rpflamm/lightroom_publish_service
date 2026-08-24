<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Controller\Api;

use Psr\Http\Message\ResponseInterface;
use RPflamm\LightroomPublishService\Domain\Model\ApiToken;
use RPflamm\LightroomPublishService\Http\ApiResponseFactory;
use TYPO3\CMS\Core\Information\Typo3Version;

final class PingController
{
    public function __construct(
        private readonly Typo3Version $typo3Version,
    ) {}

    public function ping(ApiToken $apiToken): ResponseInterface
    {
        return ApiResponseFactory::success([
            'tokenName' => $apiToken->name,
            'typo3Version' => $this->typo3Version->getVersion(),
            'serverTime' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
