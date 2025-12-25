<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor;

class Stats implements \JsonSerializable
{
    private int $processedRows = 0;
    private int $validRows = 0;
    private int $errorRows = 0;
    private ?float $startTime = null;
    private ?float $endTime = null;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    public function incrementProcessed(): void
    {
        $this->processedRows++;
    }

    public function incrementValid(): void
    {
        $this->validRows++;
    }

    public function incrementErrors(): void
    {
        $this->errorRows++;
    }

    public function finish(): void
    {
        $this->endTime = microtime(true);
    }

    // Getters
    public function getProcessedRows(): int
    {
        return $this->processedRows;
    }
    public function getValidRows(): int
    {
        return $this->validRows;
    }
    public function getErrorRows(): int
    {
        return $this->errorRows;
    }

    public function getProcessingTime(): ?float
    {
        if ($this->startTime && $this->endTime) {
            return $this->endTime - $this->startTime;
        }

        return null;
    }

    public function getSuccessRate(): float
    {
        if ($this->processedRows === 0) {
            return 0.0;
        }

        return ($this->validRows / $this->processedRows) * 100;
    }

    public function toArray(): array
    {
        return [
            'processed_rows' => $this->processedRows,
            'valid_rows' => $this->validRows,
            'error_rows' => $this->errorRows,
            'success_rate' => $this->getSuccessRate(),
            'processing_time' => $this->getProcessingTime(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
