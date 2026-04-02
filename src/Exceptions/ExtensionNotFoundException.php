<?php

declare(strict_types=1);

namespace Notur\Exceptions;

class ExtensionNotFoundException extends NoturException
{
    public readonly string $extensionId;

    public function __construct(string $extensionId, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->extensionId = $extensionId;

        if ($message === '') {
            $message = "Extension '{$extensionId}' not found.";
        }

        parent::__construct($message, $code, $previous);
    }
}
