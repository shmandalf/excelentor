<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Exceptions;

/**
 * 🔮 Casting Spell Mishap
 *
 * Thrown when a value cannot be transmuted into the desired type.
 * Contains full context for debugging and user-friendly error messages.
 */
class CastException extends \RuntimeException
{
    private ?string $propertyName;
    private ?string $expectedType;
    private mixed $actualValue;
    private ?int $lineNo;

    public function __construct(
        string $message = "",
        ?string $propertyName = null,
        ?string $expectedType = null,
        mixed $actualValue = null,
        ?int $lineNo = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->propertyName = $propertyName;
        $this->expectedType = $expectedType;
        $this->actualValue = $actualValue;
        $this->lineNo = $lineNo;
    }

    public function getPropertyName(): ?string
    {
        return $this->propertyName;
    }

    public function getExpectedType(): ?string
    {
        return $this->expectedType;
    }

    public function getActualValue(): mixed
    {
        return $this->actualValue;
    }

    public function getLineNo(): ?int
    {
        return $this->lineNo;
    }

    /**
     * Creates exception for unsupported type
     */
    public static function unsupportedType(
        string $propertyName,
        string $type,
        mixed $value,
        ?int $lineNo = null
    ): self {
        $message = sprintf(
            'Unsupported type "%s" for property "%s". Value: %s',
            $type,
            $propertyName,
            self::valueToString($value)
        );

        return new self($message, $propertyName, $type, $value, $lineNo);
    }

    /**
     * Creates exception for conversion failure
     */
    public static function conversionFailed(
        string $propertyName,
        string $type,
        mixed $value,
        ?string $details = null,
        ?int $lineNo = null
    ): self {
        $message = sprintf(
            'Cannot convert value %s to type "%s" for property "%s"',
            self::valueToString($value),
            $type,
            $propertyName
        );

        if ($details) {
            $message .= ". $details";
        }

        return new self($message, $propertyName, $type, $value, $lineNo);
    }

    /**
     * Safely converts any value to string for error messages
     */
    private static function valueToString(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        if (is_array($value)) {
            return 'array(' . count($value) . ')';
        }

        if (is_object($value)) {
            return get_class($value);
        }

        return gettype($value);
    }
}
