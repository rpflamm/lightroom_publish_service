<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Exception;

final class RateLimitExceededException extends \RuntimeException
{
    public function __construct(private readonly int $retryAfterSeconds)
    {
        parent::__construct('Too many requests.');
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
