<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Exceptions;

class ValidationException extends \Exception
{
    /**
     * Row number where the error occurred
     *
     * @var integer|null
     */
    private ?int $lineNo;

    /**
     * Data/context in which the error occurred
     *
     */
    private ?array $data;

    /**
     * Constructs the Exception.
     *
     * @param string $message The Exception message to throw.
     */
    public function __construct($message = '', ?int $lineNo = null, ?array $data = null)
    {
        parent::__construct($message);

        $this->lineNo = $lineNo;
        $this->data = $data;
    }

    /**
     * Returns the row number where the validation error occurred
     *
     */
    public function getLineNo(): ?int
    {
        return $this->lineNo;
    }

    /**
     * Returns the error data/context
     *
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    public function toArray(): array
    {
        return [
            'line' => $this->lineNo,
            'message' => $this->getMessage(),
            'data' => $this->data,
            'trace' => $this->getTraceAsString(),
        ];
    }
}
