<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Exception;

final class ValidationException extends \RuntimeException
{
    public function __construct(string $message, private readonly string $errorCode = 'validation_error')
    {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
