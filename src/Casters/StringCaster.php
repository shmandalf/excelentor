<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Casters;

use InvalidArgumentException;
use Shmandalf\Excelentor\Contracts\CasterInterface;

/**
 * 🔮 String Transmutation Spell
 *
 * Converts any spreadsheet essence into pure string mana.
 * Handles null values, booleans, numbers, and objects with __toString().
 * Empty values can be configured to return empty string or throw exception.
 */
class StringCaster implements CasterInterface
{
    private bool $allowNull;
    private bool $trim;

    /**
     * @param bool $allowNull Whether to allow null values (returns empty string)
     * @param bool $trim Whether to trim whitespace from strings
     */
    public function __construct(bool $allowNull = true, bool $trim = true)
    {
        $this->allowNull = $allowNull;
        $this->trim = $trim;
    }

    public function cast(mixed $value, ?string $format = null): string
    {
        // Null handling
        if ($value === null) {
            if ($this->allowNull) {
                return '';
            }
            throw new InvalidArgumentException('Cannot convert null to string when null is not allowed');
        }

        // Boolean handling
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        // Numeric handling (int, float)
        if (is_int($value) || is_float($value)) {
            // Simple formatting: only decimal places, no thousands separator
            if ($format !== null && is_numeric($format)) {
                return number_format($value, (int) $format, '.', '');
            }

            return (string) $value;
        }

        // String handling
        if (is_string($value)) {
            return $this->trim ? trim($value) : $value;
        }

        // Object with __toString()
        if (is_object($value) && method_exists($value, '__toString')) {
            $result = (string) $value;

            return $this->trim ? trim($result) : $result;
        }

        // Arrays and other types
        throw new InvalidArgumentException(
            sprintf('Cannot convert %s to string', gettype($value))
        );
    }
}
