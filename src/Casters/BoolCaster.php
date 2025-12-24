<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Casters;

use Shmandalf\Excelentor\Contracts\CasterInterface;
use InvalidArgumentException;

/**
 * 🔮 Boolean Transmutation Spell
 *
 * Converts spreadsheet essence into pure boolean mana.
 * Supports custom true/false values, numeric conversions,
 * and intelligent string parsing.
 */
class BoolCaster implements CasterInterface
{
    private array $trueValues;
    private array $falseValues;
    private bool $strict;

    /**
     * @param array $trueValues Array of string values considered "true"
     * @param array $falseValues Array of string values considered "false"
     * @param bool $strict Whether to throw exception on unrecognized values
     */
    public function __construct(
        ?array $trueValues = null,
        ?array $falseValues = null,
        bool $strict = true
    ) {
        $this->trueValues = $trueValues ?? ['true', 'yes', '1', 'да', '+', 'on', 'enabled', 'active'];
        $this->falseValues = $falseValues ?? ['false', 'no', '0', 'нет', '-', 'off', 'disabled', 'inactive', ''];
        $this->strict = $strict;
    }

    public function cast(mixed $value, ?string $format = null): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false; // null → false по умолчанию
        }

        if (is_int($value) || is_float($value)) {
            return $this->castNumeric($value);
        }

        if (is_string($value)) {
            return $this->castString($value);
        }

        // Для объектов и массивов - пробуем через строку
        try {
            $stringValue = (new StringCaster())->cast($value);
            return $this->castString($stringValue);
        } catch (InvalidArgumentException $e) {
            if ($this->strict) {
                throw new InvalidArgumentException(
                    sprintf('Cannot convert %s to boolean: %s', gettype($value), $e->getMessage())
                );
            }
            return false; // fallback
        }
    }

    private function castNumeric(int|float $value): bool
    {
        return !($value == 0);
    }

    private function castString(string $value): bool
    {
        $value = trim($value);
        $lowerValue = strtolower($value);

        // Проверяем кастомные true значения
        foreach ($this->trueValues as $trueValue) {
            if (strtolower($trueValue) === $lowerValue) {
                return true;
            }
        }

        // Проверяем кастомные false значения
        foreach ($this->falseValues as $falseValue) {
            if (strtolower($falseValue) === $lowerValue) {
                return false;
            }
        }

        // Если строка пустая после трима
        if ($value === '') {
            return false;
        }

        // Пробуем как число
        if (is_numeric($value)) {
            return $this->castNumeric((float) $value);
        }

        // Неизвестное значение
        if ($this->strict) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot interpret string "%s" as boolean. ' .
                        'Accepted true values: %s. ' .
                        'Accepted false values: %s.',
                    $value,
                    implode(', ', $this->trueValues),
                    implode(', ', $this->falseValues)
                )
            );
        }

        // В нестрогом режиме: любая непустая строка → true
        return true;
    }
}
