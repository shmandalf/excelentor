<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Shmandalf\Excelentor\Attributes\{Column, Header, NoHeader};
use Shmandalf\Excelentor\Contracts\ParserInterface;
use Shmandalf\Excelentor\Exceptions\{CastException, ParserException, ValidationException};

class Parser implements ParserInterface
{
    use CasterConfigurationTrait;

    /**
     * Data caster registry
     *
     * Stores instances of casters.
     * Key - string representation of the type (integer/string/etc)
     * Value - caster instance implementing CasterInterface
     *
     * @var CasterInterface[]
     */
    private array $casterRegistry = [];

    private string $mappedClass;

    /**
     * Header configuration
     *
     */
    private Header $header;

    /**
     * Column configurations
     *
     * @var Column[]
     */
    private array $columns = [];

    /**
     * Properties of the class
     *
     * @var ReflectionProperty[]
     */
    private array $properties = [];

    /**
     * Column indexes as $propName => $index
     *
     */
    private array $indexes = [];

    /**
     * Indexes of columns that must be present in a row for it to be considered "non-empty"
     *
     * @var int[]
     */
    private array $mandatoryColumns = [];

    /**
     * Validation rules
     *
     * Uses column name as key
     *
     */
    private array $rules = [];

    /**
     * Validation error messages
     *
     */
    private array $messages = [];

    /**
     * Array of properties that can accept null
     *
     */
    private array $nullableProperties = [];

    private ValidatorFactory $validatorFactory;

    /**
     * Constructor
     *
     * @param ValidatorFactory $validatorFactory
     */
    public function __construct(string $mappedClass, $validatorFactory)
    {
        $this->mappedClass = $mappedClass;
        $this->validatorFactory = $validatorFactory;

        $this->registerDefaultCasters();

        $reflectionClass = new ReflectionClass($mappedClass);
        $this->assembleHeader($reflectionClass);
        $this->assembleProperties($reflectionClass);
        $this->assembleValidation();
    }

    /**
     * Validates all input data.
     *
     * @return ValidationException[] - empty array if no errors found
     */
    public function validateAll(iterable $rows): array
    {
        $exceptions = [];

        foreach ($this->filterRows($rows) as $rowIndex => $row) {
            try {
                $this->validateRow($row, $rowIndex);
            } catch (ValidationException $e) {
                $exceptions[] = $e;
            }
        }

        return $exceptions;
    }

    /**
     * Parse rows with statistics and error handling
     *
     * @param iterable $rows Input data
     * @param callable|null $errorHandler Callback for error handling
     */
    public function parse(iterable $rows, ?callable $errorHandler = null): ParseResult
    {
        // Check if we have any properties that need casting
        $needsCasters = false;

        foreach ($this->properties as $name => $property) {
            $type = $property->getType()->getName();

            if (!isset($this->casterRegistry[$type])) {
                $needsCasters = true;
                break;
            }
        }

        if ($needsCasters) {
            throw new \RuntimeException(
                'No casters registered. Use withDefaultCasters() or withCast() methods ' .
                    'to register casters for types: ' .
                    implode(', ', array_unique(array_map(
                        fn ($p) => $p->getType()->getName(),
                        $this->properties
                    )))
            );
        }

        return new ParseResult(
            processor: function () use ($rows) {
                return $this->parseRows($rows);
            },
            errorHandler: $errorHandler
        );
    }

    /**
     * Internal method that yields either entities or ValidationException
     * This replaces the old parse() method logic
     */
    private function parseRows(iterable $rows): \Generator
    {
        foreach ($this->filterRows($rows) as $rowIndex => $row) {
            try {
                $validatedRow = $this->validateRow($row, $rowIndex);
                yield $rowIndex => $this->parseValidatedRow($validatedRow, $rowIndex);
            } catch (ValidationException $e) {
                yield $e;
            } catch (CastException $e) {
                // Convert CastException to ValidationException for consistency
                $validationException = new ValidationException(
                    $e->getMessage(),
                    $e->getLineNo(),
                    [
                        'property' => $e->getPropertyName(),
                        'expected_type' => $e->getExpectedType(),
                        'actual_value' => $e->getActualValue(),
                    ]
                );
                yield $validationException;
            } catch (\Throwable $e) {
                // Wrap any other exception
                $validationException = new ValidationException(
                    sprintf('Unexpected error: %s', $e->getMessage()),
                    $rowIndex,
                    ['original_exception' => $e->getMessage()]
                );
                yield $validationException;
            }
        }
    }

    /**
     * Validates a single row.
     *
     * Accepts a "raw" row with numeric indexes. Returns an associative array
     * with property names as keys if validation passes.
     *
     * @throws ValidationException
     */
    private function validateRow(array $row, int $rowIndex): array
    {
        // Convert $row to associative array
        $mappedRow = $this->convertIndexedRowToHavePropsNamesAsKeys($row);

        $validator = $this->validatorFactory->make($mappedRow, $this->rules, $this->messages);

        if ($validator->fails()) {
            $errorMsg = $validator->messages()->toJson(JSON_UNESCAPED_UNICODE);
            throw new ValidationException($errorMsg, $rowIndex);
        }

        return $validator->getData();
    }

    /**
     * Converts a raw array with numeric keys to associative array
     * using property names
     *
     */
    private function convertIndexedRowToHavePropsNamesAsKeys(array $row): array
    {
        $mappedRow = [];

        foreach ($this->columns as $name => $column) {
            $columnIndex = $this->indexes[$name];
            $value = $row[$columnIndex] ?? null;
            $mappedRow[$name] = $value;
        }

        return $mappedRow;
    }

    /**
     * Filters out rows that don't require processing
     *
     * @return \Generator|mixed[]
     */
    private function filterRows(iterable $rows): \Generator
    {
        foreach ($rows as $rowIndex => $row) {
            // skip Header rows
            if ($rowIndex < $this->header->getRows()) {
                continue;
            }

            // Skip rows that don't contain all mandatory values
            if (!$this->allMandatoryColumnsPresent($row)) {
                continue;
            }

            yield $rowIndex => $row;
        }
    }

    /**
     * Reads the header configuration
     *
     * @throws ParserException
     */
    private function assembleHeader(ReflectionClass $reflectionClass): self
    {
        $header = $this->getHeaderAnnotation($reflectionClass);

        if ($header === null) {
            throw new ParserException('Missing @Header or @NoHeader annotation');
        }

        $this->header = $header;

        return $this;
    }

    /**
     * Prepares properties
     *
     * @throws ParserException
     */
    private function assembleProperties(ReflectionClass $reflectionClass): self
    {
        $props = $reflectionClass->getProperties();

        foreach ($props as $prop) {
            $propName = $prop->getName();

            // Ensure the property has an explicitly declared type
            if ($prop->getType() === null) {
                throw new ParserException("Property `{$propName}` must have an explicitly declared type");
            }

            // Handle union types, e.g., string|null
            if ($this->checkNullable($prop)) {
                $this->nullableProperties[$propName] = true;
            }

            $attributes = $prop->getAttributes(Column::class);

            if (!$attributes) {
                continue;
            }

            foreach ($attributes as $attribute) {
                $column = $attribute->newInstance();

                $this->columns[$propName] = $column;
                $this->properties[$propName] = $prop;

                $colIndex = $this->header->getColumnIndex($propName);
                $this->indexes[$propName] = $colIndex;

                if ($column->isMandatory()) {
                    $this->mandatoryColumns[$propName] = $colIndex;
                }
            }
        }

        if (empty($this->columns)) {
            throw new ParserException('No @Column annotations found');
        }

        // Ensure the number of properties matches the number of columns in Header
        if (count($this->header->getColumns()) !== count($this->columns)) {
            throw new ParserException("@Header columns count doesn't match the @Column count");
        }

        return $this;
    }

    private function checkNullable(ReflectionProperty $prop): bool
    {
        $type = $prop->getType();

        if ($type === null) {
            return false; // or throw exception as you already have
        }

        // For simple types
        if ($type instanceof \ReflectionNamedType) {
            return $type->allowsNull();
        }

        // For union types (e.g., string|null)
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $subType) {
                if ($subType instanceof \ReflectionNamedType && $subType->getName() === 'null') {
                    return true;
                }
            }

            return false;
        }

        // For intersection types (PHP 8.1+)
        if ($type instanceof \ReflectionIntersectionType) {
            // null cannot be part of an intersection, but check just in case
            foreach ($type->getTypes() as $subType) {
                if ($subType instanceof \ReflectionNamedType && $subType->getName() === 'null') {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    private function assembleValidation(): self
    {
        // Validation messages from Header
        $this->messages = $this->header->getMessages();

        // Validation rules for rows
        foreach ($this->columns as $name => $column) {
            // rules
            $rule = $column->getRule();

            if ($rule !== null) {
                $this->rules[$name] = $rule;
            }

            // messages (for Column, there may also be global messages in Header)
            $messages = $column->getMessages();

            if ($messages) {
                foreach ($messages as $k => $message) {
                    $this->messages["{$name}.{$k}"] = $message;
                }
            }
        }

        return $this;
    }

    /**
     * Returns the header annotation
     *
     */
    private function getHeaderAnnotation(ReflectionClass $reflectionClass): ?Header
    {
        $header = $reflectionClass->getAttributes(Header::class)[0] ?? null;

        // If Header not set, check for NoHeader
        $header ??= $reflectionClass->getAttributes(NoHeader::class)[0] ?? null;

        return $header?->newInstance() ?? null;
    }

    /**
     * Parses a validated array that already uses property names as keys
     *
     */
    private function parseValidatedRow(array $row, int $rowIndex): object
    {
        $obj = new $this->mappedClass();

        /** @var string $name */
        foreach ($this->properties as $name => $prop) {
            $propTypeName = $prop->getType()->getName();
            $format = $this->columns[$name]->getFormat();

            // Check for null, not falsy!
            $value = $row[$name] ?? $this->getDefaultValue($obj, $prop);

            // If empty string and property is nullable - convert to null
            if ($value === '' && ($this->nullableProperties[$name] ?? false)) {
                $value = null;
            }

            $value = $this->castValueToPropType($value, $propTypeName, $format, $name, $rowIndex);

            try {
                $prop->setValue($obj, $value);
            } catch (\Throwable $e) {
                throw new ValidationException($e->getMessage(), $rowIndex);
            }
        }

        return $obj;
    }

    /**
     * Updated casting method - throws if no caster found
     */
    private function castValueToPropType($value, string $type, ?string $format = null, string $propertyName = '', int $rowIndex = 0)
    {
        // Null handling (оставляем как есть)
        if ($value === null) {
            if ($this->nullableProperties[$propertyName] ?? false) {
                return null;
            }
            throw CastException::conversionFailed(
                $propertyName,
                $type,
                $value,
                'Value cannot be null for non-nullable property',
                $rowIndex
            );
        }

        // Find caster
        if (!isset($this->casterRegistry[$type])) {
            throw CastException::unsupportedType(
                $propertyName,
                $type,
                $value,
                $rowIndex,
                'No caster registered for this type. Use withCast() method.'
            );
        }

        $caster = $this->casterRegistry[$type];

        try {
            return $caster->cast($value, $format);
        } catch (\InvalidArgumentException $e) {
            throw CastException::conversionFailed(
                $propertyName,
                $type,
                $value,
                $e->getMessage(),
                $rowIndex
            );
        } catch (\Throwable $e) {
            throw CastException::conversionFailed(
                $propertyName,
                $type,
                $value,
                sprintf('Unexpected error: %s', $e->getMessage()),
                $rowIndex
            );
        }
    }

    /**
     * Returns the default value of a property
     *
     * May return null if the property is not set
     *
     */
    private function getDefaultValue(object $instance, ReflectionProperty $property)
    {
        if ($property->isInitialized($instance)) {
            try {
                // Try to get the property's default value
                return $property->getValue($instance);
            } catch (ReflectionException $e) {
                // No default value - return null
                return null;
            }
        }

        return null;
    }

    /**
     * Returns true if the row is "non-empty", i.e., all mandatory columns have non-empty values
     *
     * @return boolean
     */
    private function allMandatoryColumnsPresent(array $row): bool
    {
        foreach ($this->mandatoryColumns as $index) {
            if (empty($row[$index])) {
                return false;
            }
        }

        return true;
    }
}
