<?php

declare(strict_types=1);

namespace WhmcsXtreamAI;

final class PanelApiRequestException extends \RuntimeException
{
    
    private $httpStatus;

    
    private $errorType;

    public function __construct(
        string $message,
        int $httpStatus,
        string $errorType,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatus, $previous);
        $this->httpStatus = $httpStatus;
        $this->errorType = $errorType;
    }

    

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    

    public function getErrorType(): string
    {
        return $this->errorType;
    }
}
