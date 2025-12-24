<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor;

use Carbon\Carbon;
use Shmandalf\Excelentor\Attributes\{Column, Header, NoHeader};
use Shmandalf\Excelentor\Casters\DateCaster;
use Shmandalf\Excelentor\Contracts\{CasterInterface, ParserInterface};
use Shmandalf\Excelentor\Exceptions\{CastException, ParserException, ValidationException};
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Shmandalf\Excelentor\Casters\BoolCaster;
use Shmandalf\Excelentor\Casters\FloatCaster;
use Shmandalf\Excelentor\Casters\IntCaster;
use Shmandalf\Excelentor\Casters\StringCaster;
use Shmandalf\Excelentor\ValidatorFactory;

class Parser implements ParserInterface
{
    /**
     * Data caster registry
     *
     * Хранит инстансы кастеров.
     * Ключ - строковое представление типа (integer/string/etc)
     * Значение - инстанс кастера CasterInterface
     *
     * @var CasterInterface[]
     */
    private array $casterRegistry = [];

    private string $mappedClass;

    /**
     * Header
     *
     * @var Header
     */
    private Header $header;

    /**
     * Columns
     *
     * @var Column[]
     */
    private array $columns;

    /**
     * Properties of the class
     *
     * @var ReflectionProperty[]
     */
    private array $properties;

    /**
     * Индексы столбцов в виде $propName => $index
     *
     * @var array
     */
    private array $indexes = [];

    /**
     * Индексы столбцов, которые должны присутствовать в строке, чтобы она считалась "не пустой"
     *
     * @var int[]
     */
    private array $mandatoryColumns = [];

    /**
     * Правила валидации
     *
     * Использует имя столбца в качестве ключа
     *
     * @var array
     */
    private array $rules = [];

    /**
     * Сообщения об ошибках валидации
     *
     * @var array
     */
    private array $messages = [];

    /**
     * Массив свойств, которые могут принимать null
     */
    private array $nullableProperties = [];

    private ValidatorFactory $validatorFactory;

    /**
     * Конструктор
     *
     * @param string    $mappedClass
     * @param ValidatorFactory $validatorFactory
     */
    public function __construct(string $mappedClass, $validatorFactory)
    {
        $this->mappedClass = $mappedClass;
        $this->validatorFactory = $validatorFactory;

        $reflectionClass = new ReflectionClass($mappedClass);

        $this->registerCasters();
        $this->assembleHeader($reflectionClass);
        $this->assembleProperties($reflectionClass);
        $this->assembleValidation();
    }

    /**
     * Проверяет входные данные.
     *
     * @return ValidationException[] - или пустой массив, если ошибок не обнаружено
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

    public function parse(iterable $rows): \Generator
    {
        foreach ($this->filterRows($rows) as $rowIndex => $row) {
            try {
                $validatedRow = $this->validateRow($row, $rowIndex);
                yield $rowIndex => $this->parseValidatedRow($validatedRow, $rowIndex);
            } catch (ValidationException $e) {
                if ($this->header->shouldStopOnFirstFailure()) {
                    throw $e;
                }
                continue;
            } catch (CastException $e) {
                $validationException = new ValidationException(
                    $e->getMessage(),
                    $e->getLineNo(),
                    [
                        'property' => $e->getPropertyName(),
                        'expected_type' => $e->getExpectedType(),
                        'actual_value' => $e->getActualValue(),
                    ]
                );

                if ($this->header->shouldStopOnFirstFailure()) {
                    throw $validationException;
                }
                continue;
            } catch (\Throwable $e) {
                if ($this->header->shouldStopOnFirstFailure()) {
                    throw $e;
                }
                continue;
            }
        }
    }

    /**
     * Валидирует строку.
     *
     * На входе получает "сырую" строку с числовыми индексами. В случае успешного прохождения
     * валидации возвращает ассоциативный массив с именами пропсов в виде ключей.
     *
     * @param array $row
     * @param int   $rowIndex
     * @return array
     * @throws ValidationException
     */
    private function validateRow(array $row, int $rowIndex): array
    {
        // Преобразуем $row в ассоциативный массив
        $mappedRow = $this->convertIndexedRowToHavePropsNamesAsKeys($row);

        $validator = $this->validatorFactory->make($mappedRow, $this->rules, $this->messages);

        if ($validator->fails()) {
            $errorMsg = $validator->messages()->toJson(JSON_UNESCAPED_UNICODE);
            throw new ValidationException($errorMsg, $rowIndex);
        }

        return $validator->getData();
    }

    /**
     * Преобразовывает исходный "сырой" массив с числовыми ключами в ассоциативный,
     * используя имена свойств
     *
     * @param array $row
     * @return array
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
     * Исключает строки, которые не требуют обработки
     *
     * @param iterable $rows
     * @return \Generator|mixed[]
     */
    private function filterRows(iterable $rows): \Generator
    {
        foreach ($rows as $rowIndex => $row) {
            // пропускаем Header rows
            if ($rowIndex < $this->header->getRows()) {
                continue;
            }

            // Не обрабатываем строки, которые не содержат всех обязательных значений
            if (!$this->allMandatoryColumnsPresent($row)) {
                continue;
            }

            yield $rowIndex => $row;
        }
    }

    /**
     * Создает инстансы кастеров
     *
     * @return self
     */
    private function registerCasters(): self
    {
        // Built-in type casters
        $this->casterRegistry['int'] = new IntCaster();
        $this->casterRegistry['integer'] = $this->casterRegistry['int'];
        $this->casterRegistry['float'] = new FloatCaster();
        $this->casterRegistry['double'] = $this->casterRegistry['float'];
        $this->casterRegistry['bool'] = new BoolCaster();
        $this->casterRegistry['boolean'] = $this->casterRegistry['bool'];
        $this->casterRegistry['string'] = new StringCaster();

        // Date/Time casters
        $this->casterRegistry[Carbon::class] = new DateCaster();
        $this->casterRegistry[\DateTime::class] = $this->casterRegistry[Carbon::class];
        $this->casterRegistry[\DateTimeImmutable::class] = $this->casterRegistry[Carbon::class];

        return $this;
    }
    /**
     * Читаем заголовок
     *
     * @param  ReflectionClass $reflectionClass
     * @return self
     * @throws ParserException
     */
    private function assembleHeader(ReflectionClass $reflectionClass): self
    {
        $header = $this->getHeaderAnnotation($reflectionClass);

        if ($header === null) {
            throw new ParserException("Missing @Header or @NoHeader annotation");
        }

        $this->header = $header;

        return $this;
    }

    /**
     * Подготавливаем пропсы.
     *
     * @param ReflectionClass $reflectionClass
     * @return self
     * @throws ParserException
     */
    private function assembleProperties(ReflectionClass $reflectionClass): self
    {
        $props = $reflectionClass->getProperties();

        foreach ($props as $prop) {
            $propName = $prop->getName();

            // Удостоверимся, что у свойства явно указан тип
            if ($prop->getType() === null) {
                throw new ParserException("Необходимо явно указать тип свойства `{$propName}`");
            }

            // Обработка union types, например string|null
            if ($this->checkNullable($prop)) {
                $this->nullableProperties[$propName] = true;
            }

            $attributes = $prop->getAttributes(Column::class);

            if (!$attributes) continue;

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
            throw new ParserException("No @Column annotations found");
        }

        // Удостоверимся, что число пропсов совпадает с числом столбцов в Header
        if (sizeof($this->header->getColumns()) !== sizeof($this->columns)) {
            throw new ParserException("@Header columns count doesn't match the @Column count");
        }

        return $this;
    }

    private function checkNullable(ReflectionProperty $prop): bool
    {
        $type = $prop->getType();

        if ($type === null) {
            return false; // или бросать исключение, как у тебя уже есть
        }

        // Для простых типов
        if ($type instanceof \ReflectionNamedType) {
            return $type->allowsNull();
        }

        // Для union types (например string|null)
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $subType) {
                if ($subType instanceof \ReflectionNamedType && $subType->getName() === 'null') {
                    return true;
                }
            }
            return false;
        }

        // Для intersection types (PHP 8.1+)
        if ($type instanceof \ReflectionIntersectionType) {
            // В intersection типах null не может быть частью, но на всякий случай
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
        // Сообщения валидации из Header
        $this->messages = $this->header->getMessages();

        // Правила валидации для строк
        foreach ($this->columns as $name => $column) {
            // rules
            $rule = $column->getRule();

            if ($rule !== null) {
                $this->rules[$name] = $rule;
            }

            // messages (для Col, еще могут быть заданы global messages в Header)
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
     * Возвращает annotation для заголовка
     *
     * @param  ReflectionClass  $reflectionClass
     * @return Header|null
     */
    private function getHeaderAnnotation(ReflectionClass $reflectionClass): ?Header
    {
        $header = $reflectionClass->getAttributes(Header::class)[0] ?? null;

        // Если Header не задан, проверяем NoHeader
        $header ??= $reflectionClass->getAttributes(NoHeader::class)[0] ?? null;

        return $header?->newInstance() ?? null;
    }

    /**
     * Парсинг валидированного массива, уже использующего имена пропсов в виде ключей
     *
     * @param  array  $row
     * @param  int    $rowIndex
     * @return object
     */
    private function parseValidatedRow(array $row, int $rowIndex): object
    {
        $obj = new $this->mappedClass;

        /** @var string $name */
        foreach ($this->properties as $name => $prop) {
            $propTypeName = $prop->getType()->getName();
            $format = $this->columns[$name]->getFormat();

            // Проверяем на null, а не на falsy!
            $value = $row[$name] ?? $this->getDefaultValue($obj, $prop);

            // Если пустая строка и свойство nullable - преобразуем в null
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
     * Преобразовывает строковое значение из spreadsheet в определенный тип
     *
     * @param mixed       $value
     * @param string      $type
     * @param string|null $format
     * @return mixed
     */
    private function castValueToPropType($value, string $type, ?string $format = null, string $propertyName = '', int $rowIndex = 0)
    {
        // Null handling
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

        // Find appropriate caster
        if (isset($this->casterRegistry[$type])) {
            try {
                return $this->casterRegistry[$type]->cast($value, $format);
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

        // Check built-in PHP types
        if (in_array($type, ['int', 'integer', 'float', 'double', 'bool', 'boolean', 'string'], true)) {
            throw CastException::unsupportedType(
                $propertyName,
                $type,
                $value,
                $rowIndex
            );
        }

        // For class types without caster
        return $value;
    }

    /**
     * Возвращает значение свойства по умолчанию
     *
     * Может вернуть null если свойство не задано
     *
     * @param object $instance
     * @param ReflectionProperty $property
     * @return mixed
     */
    private function getDefaultValue(object $instance, ReflectionProperty $property)
    {
        if ($property->isInitialized($instance)) {
            try {
                // Пытаемся получить default value свойства
                return $property->getValue($instance);
            } catch (ReflectionException $e) {
                // Нет default value - возвращаем null
                return null;
            }
        }

        return null;
    }

    /**
     * Возвращает true, если строка "не пустая", т.е. все обязательные столбцы имеют непустые значения
     *
     * @param  array $row
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
