<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Exceptions;

class ValidationException extends \Exception
{
    /**
     * Номер строки с ошибкой
     *
     * @var integer|null
     */
    private ?int $lineNo;

    /**
     * Данные/контекст, где возникла ошибка
     *
     * @var array|null
     */
    private ?array $data;

    /**
     * Constructs the Exception.
     *
     * @param string $message The Exception message to throw.
     */
    function __construct($message = "", ?int $lineNo = null, ?array $data = null)
    {
        parent::__construct($message);

        $this->lineNo = $lineNo;
        $this->data = $data;
    }

    /**
     * Возвращает номер строки, в которой произошла ошибка валидации
     *
     * @return int|null
     */
    public function getLineNo(): ?int
    {
        return $this->lineNo;
    }

    /**
     * Возвращает данные/контекст ошибки
     *
     * @return array|null
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
