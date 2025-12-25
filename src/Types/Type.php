<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Types;

use Carbon\Carbon;

enum Type: string
{
    // Primitive types
    case INT = 'int';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case DOUBLE = 'double';
    case BOOL = 'bool';
    case BOOLEAN = 'boolean';
    case STRING = 'string';

    // Class types
    case CARBON = Carbon::class;
    case DATETIME = \DateTime::class;
    case DATETIME_IMMUTABLE = \DateTimeImmutable::class;

    // Aliases (groups)
    case DATE = 'date';
    case NUMBER = 'number';

    /**
     * Resolve type to actual PHP types
     */
    public function resolve(): array
    {
        return match ($this) {
            // Aliases
            self::DATE => [
                self::CARBON->value,
                self::DATETIME->value,
                self::DATETIME_IMMUTABLE->value,
            ],
            self::NUMBER => [
                'int',
                'integer',
                'float',
                'double',
            ],

            // Primitives with aliases
            self::INT, self::INTEGER => ['int', 'integer'],
            self::FLOAT, self::DOUBLE => ['float', 'double'],
            self::BOOL, self::BOOLEAN => ['bool', 'boolean'],

            // Everything else - just the value
            default => [$this->value]
        };
    }

    /**
     * Try to create Type from string (class name or primitive)
     * Returns null if not a built-in type
     */
    public static function tryFromString(string $value): ?self
    {
        // First try exact match
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }

        // Try case-insensitive for primitives
        $lowerValue = strtolower($value);

        foreach (self::cases() as $case) {
            if (strtolower($case->value) === $lowerValue) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Check if string is a known type
     */
    public static function isKnownType(string $value): bool
    {
        return self::tryFromString($value) !== null;
    }

    /**
     * Get all date types
     */
    public static function dateTypes(): array
    {
        return [self::CARBON, self::DATETIME, self::DATETIME_IMMUTABLE];
    }

    /**
     * Get all number types
     */
    public static function numberTypes(): array
    {
        return [self::INT, self::INTEGER, self::FLOAT, self::DOUBLE];
    }

    /**
     * Get all boolean types
     */
    public static function boolTypes(): array
    {
        return [self::BOOL, self::BOOLEAN];
    }

    /**
     * Get all string types
     */
    public static function stringTypes(): array
    {
        return [self::STRING];
    }

    /**
     * Check if type is a primitive
     */
    public function isPrimitive(): bool
    {
        return in_array($this, [
            self::INT,
            self::INTEGER,
            self::FLOAT,
            self::DOUBLE,
            self::BOOL,
            self::BOOLEAN,
            self::STRING,
        ], true);
    }

    /**
     * Check if type is a date/time
     */
    public function isDate(): bool
    {
        return in_array($this, [
            self::CARBON,
            self::DATETIME,
            self::DATETIME_IMMUTABLE,
            self::DATE,
        ], true);
    }

    /**
     * Check if type is a number
     */
    public function isNumber(): bool
    {
        return in_array($this, [
            self::INT,
            self::INTEGER,
            self::FLOAT,
            self::DOUBLE,
            self::NUMBER,
        ], true);
    }

    /**
     * Create Type from string or return custom type wrapper
     * For custom types, returns a special wrapper object
     */
    public static function fromClass(string $classNameOrPrimitive): string|Type
    {
        $type = self::tryFromString($classNameOrPrimitive);

        if ($type !== null) {
            return $type;
        }

        // For custom classes, return the string as-is
        // Parser::withCast() will handle string types
        return $classNameOrPrimitive;
    }
}
