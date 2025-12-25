<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Casters;

use InvalidArgumentException;
use Shmandalf\Excelentor\Contracts\CasterInterface;

class IntCaster implements CasterInterface
{
    private bool $allowNull;
    private ?int $min;
    private ?int $max;

    public function __construct(
        bool $allowNull = false,
        ?int $min = null,
        ?int $max = null
    ) {
        $this->allowNull = $allowNull;
        $this->min = $min;
        $this->max = $max;
    }

    public function cast(mixed $value, ?string $format = null): int
    {
        if ($value === null) {
            if ($this->allowNull) {
                return 0;
            }
            throw new InvalidArgumentException('Cannot convert null to integer');
        }

        if (is_int($value)) {
            return $this->validateRange($value);
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_float($value)) {
            return $this->castFloat($value);
        }

        if (is_string($value)) {
            return $this->castString($value);
        }

        try {
            $stringValue = (new StringCaster())->cast($value);

            return $this->castString($stringValue);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(
                sprintf('Cannot convert %s to integer: %s', gettype($value), $e->getMessage())
            );
        }
    }

    private function castFloat(float $value): int
    {
        // Check if it's actually an integer (no decimal part)
        if (floor($value) != $value) {
            throw new InvalidArgumentException(
                sprintf('Float value "%s" has decimal part', $value)
            );
        }

        // Check range
        if ($value < PHP_INT_MIN || $value > PHP_INT_MAX) {
            throw new InvalidArgumentException(
                sprintf('Float value "%s" is out of integer range', $value)
            );
        }

        $result = (int)$value;

        return $this->validateRange($result);
    }

    private function castString(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            if ($this->allowNull) {
                return 0;
            }
            throw new InvalidArgumentException('Empty string cannot be converted to integer');
        }

        // First check: if it looks like a decimal number (contains dot and valid decimal)
        if (preg_match('/^-?\d+\.\d+$/', $value)) {
            // Check if it's actually an integer (e.g., "1.0", "2.00")
            $floatVal = (float) $value;

            if (floor($floatVal) != $floatVal) {
                throw new InvalidArgumentException(
                    sprintf('String value "%s" has decimal part', $value)
                );
            }
            // It's like "1.0" - convert
            $cleaned = str_replace('.', '', $value);
        } else {
            // Remove thousands separators
            $cleaned = str_replace([' ', ',', '.', "'", "\xC2\xA0"], '', $value);
        }

        if (!is_numeric($cleaned)) {
            throw new InvalidArgumentException(
                sprintf('String value "%s" is not numeric', $value)
            );
        }

        $filtered = filter_var($cleaned, FILTER_VALIDATE_INT);

        if ($filtered === false) {
            throw new InvalidArgumentException(
                sprintf('Value "%s" causes integer overflow', $value)
            );
        }

        return $this->validateRange($filtered);
    }

    private function validateRange(int $value): int
    {
        if ($this->min !== null && $value < $this->min) {
            throw new InvalidArgumentException(
                sprintf('Value %d is less than minimum %d', $value, $this->min)
            );
        }

        if ($this->max !== null && $value > $this->max) {
            throw new InvalidArgumentException(
                sprintf('Value %d is greater than maximum %d', $value, $this->max)
            );
        }

        return $value;
    }
}
