<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Casters;

use Shmandalf\Excelentor\Contracts\CasterInterface;
use InvalidArgumentException;

/**
 * 🔮 Float Transmutation Spell
 *
 * Converts spreadsheet essence into precise float mana.
 * Handles locales, thousands separators, scientific notation,
 * and magical floating-point validations.
 */
class FloatCaster implements CasterInterface
{
    private bool $allowNull;
    private ?float $min;
    private ?float $max;
    private bool $allowInfinity;
    private bool $allowNaN;
    private string $decimalSeparator;
    private string $thousandsSeparator;

    /**
     * @param bool $allowNull Whether null returns 0.0 or throws
     * @param float|null $min Minimum allowed value
     * @param float|null $max Maximum allowed value
     * @param bool $allowInfinity Whether to allow INF/-INF values
     * @param bool $allowNaN Whether to allow NaN values
     * @param string $decimalSeparator Decimal separator for string parsing ('.' or ',')
     * @param string $thousandsSeparator Thousands separator for string parsing
     */
    public function __construct(
        bool $allowNull = false,
        ?float $min = null,
        ?float $max = null,
        bool $allowInfinity = false,
        bool $allowNaN = false,
        string $decimalSeparator = '.',
        string $thousandsSeparator = ''
    ) {
        $this->allowNull = $allowNull;
        $this->min = $min;
        $this->max = $max;
        $this->allowInfinity = $allowInfinity;
        $this->allowNaN = $allowNaN;
        $this->decimalSeparator = $decimalSeparator;
        $this->thousandsSeparator = $thousandsSeparator;
    }

    public function cast(mixed $value, ?string $format = null): float
    {
        $result = null;

        if ($value === null) {
            $result = $this->handleNull();
        } elseif (is_float($value)) {
            $result = $this->handleFloat($value);
        } elseif (is_int($value)) {
            $result = $this->handleInt($value);
        } elseif (is_bool($value)) {
            $result = $this->handleBool($value);
        } elseif (is_string($value)) {
            $result = $this->handleString($value);
        }

        if ($result === null) {
            // Try via StringCaster as fallback
            try {
                $stringValue = (new StringCaster())->cast($value);
                $result = $this->handleString($stringValue);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException(
                    sprintf('Cannot convert %s to float: %s', gettype($value), $e->getMessage())
                );
            }
        }

        // Apply formatting AFTER getting the result, for ALL types
        if ($format !== null) {
            $result = $this->applyFormat($result, $format);
        }

        return $this->validateRange($result);
    }

    // Убираем применение формата из handleString
    private function handleString(string $value): float
    {
        $value = trim($value);

        if ($value === '') {
            // Пустая строка - FloatCaster не должен обрабатывать null!
            // Null обрабатывается в Parser до вызова кастера
            throw new InvalidArgumentException('Empty string cannot be converted to float');
        }

        // Check for special string representations
        $lower = strtolower($value);
        if ($lower === 'nan') {
            return $this->handleFloat(NAN);
        }

        if ($lower === 'inf' || $lower === '+inf' || $lower === 'infinity') {
            return $this->handleFloat(INF);
        }

        if ($lower === '-inf' || $lower === '-infinity') {
            return $this->handleFloat(-INF);
        }

        // Normalize the string for parsing
        $normalized = $this->normalizeNumberString($value);

        // Try to parse as float
        $floatValue = $this->parseFloat($normalized);

        if ($floatValue === null) {
            throw new InvalidArgumentException(
                sprintf('String value "%s" is not a valid float', $value)
            );
        }

        return $floatValue; // Не применяем validateRange здесь - будет в конце cast
    }

    // Также обновляем handleFloat чтобы не дублировать validateRange
    private function handleFloat(float $value): float
    {
        // Check special values
        if (is_nan($value)) {
            if (!$this->allowNaN) {
                throw new InvalidArgumentException('NaN values are not allowed');
            }
            return NAN;
        }

        if (is_infinite($value)) {
            if (!$this->allowInfinity) {
                throw new InvalidArgumentException('Infinity values are not allowed');
            }
            return $value;
        }

        return $value; // Не применяем validateRange здесь
    }

    private function handleNull(): float
    {
        if ($this->allowNull) {
            return 0.0;
        }
        throw new InvalidArgumentException('Cannot convert null to float');
    }

    private function handleInt(int $value): float
    {
        return $this->validateRange((float) $value);
    }

    private function handleBool(bool $value): float
    {
        return $value ? 1.0 : 0.0;
    }

    private function normalizeNumberString(string $value): string
    {
        // Remove thousands separators
        if ($this->thousandsSeparator !== '') {
            $value = str_replace($this->thousandsSeparator, '', $value);
        }

        // Standardize decimal separator to dot
        if ($this->decimalSeparator !== '.') {
            $value = str_replace($this->decimalSeparator, '.', $value);
        }

        // Remove any remaining thousands separators (spaces, commas, apostrophes)
        $value = str_replace([' ', ',', "'", "\xC2\xA0"], '', $value);

        return $value;
    }

    private function parseFloat(string $value): ?float
    {
        // Check scientific notation
        if (preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?$/', $value)) {
            return (float) $value;
        }

        // Check regular float
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function applyFormat(float $value, string $format): float
    {
        // Format can be used for rounding or other transformations
        // Example formats: "2" = round to 2 decimals, "0" = round to integer

        if (is_numeric($format)) {
            $decimals = (int) $format;
            if ($decimals >= 0) {
                return round($value, $decimals);
            }
        }

        // Could add more format options later
        return $value;
    }

    private function validateRange(float $value): float
    {
        if ($this->min !== null && $value < $this->min) {
            throw new InvalidArgumentException(
                sprintf('Value %s is less than minimum %s', $value, $this->min)
            );
        }

        if ($this->max !== null && $value > $this->max) {
            throw new InvalidArgumentException(
                sprintf('Value %s is greater than maximum %s', $value, $this->max)
            );
        }

        return $value;
    }
}
