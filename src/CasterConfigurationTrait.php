<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor;

use Carbon\Carbon;
use ReflectionClass;
use ReflectionProperty;
use Shmandalf\Excelentor\Attributes\CasterConfig;
use Shmandalf\Excelentor\Attributes\Column;
use Shmandalf\Excelentor\Casters\{
    BoolCaster,
    DateCaster,
    FloatCaster,
    IntCaster,
    StringCaster
};
use Shmandalf\Excelentor\Contracts\CasterInterface;
use Shmandalf\Excelentor\Types\Type;

/**
 * Trait for Parser caster configuration
 * Provides fluent interface for configuring casters
 */
trait CasterConfigurationTrait
{
    private array $casterRegistry = [];

    /**
     * @var array<string, CasterInterface> Caster registry from CasterConfig
     */
    private array $configBasedCasterRegistry = [];

    /**
     * @var array<string, array> Caster configurations from CasterConfig attributes
     * Format: ['alias' => [casterClass, ...args], 'Type::class' => [casterClass, ...args]]
     */
    private array $casterConfigs = [];

    /**
     * Register default casters (int, float, bool, string, Carbon)
     * Called automatically in Parser constructor
     */
    private function registerDefaultCasters(): void
    {
        // Default casters
        $this->casterRegistry['int'] = new IntCaster();
        $this->casterRegistry['integer'] = $this->casterRegistry['int'];
        $this->casterRegistry['float'] = new FloatCaster();
        $this->casterRegistry['double'] = $this->casterRegistry['float'];
        $this->casterRegistry['bool'] = new BoolCaster();
        $this->casterRegistry['boolean'] = $this->casterRegistry['bool'];
        $this->casterRegistry['string'] = new StringCaster();

        // Date/Time casters
        $dateCaster = new DateCaster();
        $this->casterRegistry[Carbon::class] = $dateCaster;
        $this->casterRegistry[\DateTime::class] = $dateCaster;
        $this->casterRegistry[\DateTimeImmutable::class] = $dateCaster;
    }

    /**
     * Register a caster for specific types
     *
     * This method adds or overrides casters for the given types.
     * If a type already has a caster from CasterConfig attribute,
     * this method will override it.
     *
     * @param CasterInterface $caster The caster instance
     * @param string|Type ...$types Types this caster handles
     * @return self New Parser instance with updated caster configuration
     */
    public function withCast(CasterInterface $caster, string|Type ...$types): self
    {
        $newParser = clone $this;

        foreach ($types as $type) {
            if ($type instanceof Type) {
                $resolvedTypes = $type->resolve();
            } else {
                $resolvedTypes = [$type];
            }

            foreach ($resolvedTypes as $resolvedType) {
                // Main registry has highest priority
                $newParser->casterRegistry[$resolvedType] = $caster;

                // We don't update casterConfigs because:
                // 1. casterRegistry is checked first in getCasterForType()
                // 2. This preserves the original CasterConfig definition
                // 3. User can still see what was originally configured
            }
        }

        return $newParser;
    }

    /**
     * Remove casters for specific types
     *
     * @param string|Type ...$types Types to remove
     */
    public function withoutCast(string|Type ...$types): self
    {
        $newParser = clone $this;

        foreach ($types as $type) {
            if ($type instanceof Type) {
                $resolvedTypes = $type->resolve();
            } else {
                $resolvedTypes = [$type];
            }

            foreach ($resolvedTypes as $resolvedType) {
                unset($newParser->casterRegistry[$resolvedType]);
            }
        }

        return $newParser;
    }

    /**
     * Reset to default casters
     *
     */
    public function withDefaultCasters(): self
    {
        $newParser = clone $this;
        $newParser->casterRegistry = [];
        $newParser->registerDefaultCasters();

        return $newParser;
    }

    /**
     * Check if caster is registered for type
     *
     * @param string|Type $type Type to check
     */
    public function hasCasterFor(string|Type $type): bool
    {
        if ($type instanceof Type) {
            $resolvedTypes = $type->resolve();
        } else {
            $resolvedTypes = [$type];
        }

        foreach ($resolvedTypes as $resolvedType) {
            if (isset($this->casterRegistry[$resolvedType])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Configure date caster
     *
     * @param string|null $timezone Target timezone
     * @param array $fallbackFormats Additional date formats
     */
    public function withDateCaster(
        ?string $timezone = null,
        array $fallbackFormats = []
    ): self {
        return $this->withCast(
            new DateCaster($timezone, $fallbackFormats),
            Type::DATE
        );
    }

    /**
     * Configure European number format (1 234,56)
     *
     * @param string $decimalSeparator Decimal separator (default: ',')
     * @param string $thousandsSeparator Thousands separator (default: ' ')
     */
    public function withEuropeanNumbers(
        string $decimalSeparator = ',',
        string $thousandsSeparator = ' '
    ): self {
        return $this
            ->withoutCast(Type::FLOAT)
            ->withCast(
                new FloatCaster(decimalSeparator: $decimalSeparator, thousandsSeparator: $thousandsSeparator),
                Type::FLOAT
            );
    }

    /**
     * Configure US number format (1,234.56)
     *
     * @param string $decimalSeparator Decimal separator (default: '.')
     * @param string $thousandsSeparator Thousands separator (default: ',')
     */
    public function withUSNumbers(
        string $decimalSeparator = '.',
        string $thousandsSeparator = ','
    ): self {
        return $this->withEuropeanNumbers($decimalSeparator, $thousandsSeparator);
    }

    /**
     * Configure strict boolean caster
     * Only accepts: 'true', 'false', '1', '0', 'yes', 'no', 'on', 'off'
     *
     */
    public function withStrictBooleans(): self
    {
        $strictBoolCaster = new class () implements CasterInterface {
            public function cast($value, ?string $format = null): bool
            {
                $value = strtolower(trim((string)$value));

                $trueValues = ['true', '1', 'yes', 'on'];
                $falseValues = ['false', '0', 'no', 'off', ''];

                if (in_array($value, $trueValues, true)) {
                    return true;
                }

                if (in_array($value, $falseValues, true)) {
                    return false;
                }

                throw new \InvalidArgumentException(
                    sprintf(
                        'Invalid boolean value: "%s". Accepted values: %s',
                        $value,
                        implode(', ', array_merge($trueValues, $falseValues))
                    )
                );
            }
        };

        return $this
            ->withoutCast(Type::BOOL)
            ->withCast($strictBoolCaster, Type::BOOL);
    }

    /**
     * Configure integer caster with range validation
     *
     * @param int|null $min Minimum value
     * @param int|null $max Maximum value
     */
    public function withIntRange(?int $min = null, ?int $max = null): self
    {
        $intCaster = new class ($min, $max) extends IntCaster {
            private ?int $min;
            private ?int $max;

            public function __construct(?int $min, ?int $max)
            {
                parent::__construct();
                $this->min = $min;
                $this->max = $max;
            }

            public function cast($value, ?string $format = null): int
            {
                $result = parent::cast($value, $format);

                if ($this->min !== null && $result < $this->min) {
                    throw new \InvalidArgumentException(
                        sprintf('Value must be at least %d, got %d', $this->min, $result)
                    );
                }

                if ($this->max !== null && $result > $this->max) {
                    throw new \InvalidArgumentException(
                        sprintf('Value must be at most %d, got %d', $this->max, $result)
                    );
                }

                return $result;
            }
        };

        return $this
            ->withoutCast(Type::INT)
            ->withCast($intCaster, Type::INT);
    }

    /**
     * Configure float caster with precision rounding
     *
     * @param int $precision Number of decimal places
     * @param int $mode Rounding mode (PHP_ROUND_HALF_UP, etc.)
     */
    public function withFloatPrecision(
        int $precision = 2,
        int $mode = PHP_ROUND_HALF_UP
    ): self {
        $floatCaster = new class ($precision, $mode) extends FloatCaster {
            private int $precision;
            private int $mode;

            public function __construct(int $precision, int $mode)
            {
                parent::__construct();
                $this->precision = $precision;
                $this->mode = $mode;
            }

            public function cast($value, ?string $format = null): float
            {
                $result = parent::cast($value, $format);

                return round($result, $this->precision, $this->mode);
            }
        };

        return $this
            ->withoutCast(Type::FLOAT)
            ->withCast($floatCaster, Type::FLOAT);
    }

    /**
     * Configure string caster with trimming and case options
     *
     * @param bool $trim Enable trimming
     * @param string|null $case 'lower', 'upper', or null
     */
    public function withStringProcessing(
        bool $trim = true,
        ?string $case = null
    ): self {
        $stringCaster = new class ($trim, $case) extends StringCaster {
            private bool $trim;
            private ?string $case;

            public function __construct(bool $trim, ?string $case)
            {
                parent::__construct();
                $this->trim = $trim;
                $this->case = $case;
            }

            public function cast($value, ?string $format = null): string
            {
                $result = parent::cast($value, $format);

                if ($this->trim) {
                    $result = trim($result);
                }

                if ($this->case === 'lower') {
                    $result = strtolower($result);
                } elseif ($this->case === 'upper') {
                    $result = strtoupper($result);
                }

                return $result;
            }
        };

        return $this
            ->withoutCast(Type::STRING)
            ->withCast($stringCaster, Type::STRING);
    }

    /**
     * Get all registered caster types
     *
     */
    public function getRegisteredTypes(): array
    {
        return array_keys($this->casterRegistry);
    }

    /**
     * Internal method to cast value using registered casters
     * This should be called from Parser::castValueToPropType
     */
    private function castUsingRegistry(
        $value,
        string $type,
        ?string $format = null,
        string $propertyName = '',
        int $rowIndex = 0
    ) {
        // Null handling
        if ($value === null) {
            if (($this->nullableProperties[$propertyName] ?? false)) {
                return null;
            }
            throw new \Shmandalf\Excelentor\Exceptions\CastException(
                sprintf('Value cannot be null for non-nullable property "%s"', $propertyName),
                $propertyName,
                $type,
                $value,
                $rowIndex
            );
        }

        // Find caster
        if (!isset($this->casterRegistry[$type])) {
            throw new \Shmandalf\Excelentor\Exceptions\CastException(
                sprintf('No caster registered for type "%s". Use withCast() method.', $type),
                $propertyName,
                $type,
                $value,
                $rowIndex
            );
        }

        $caster = $this->casterRegistry[$type];

        try {
            return $caster->cast($value, $format);
        } catch (\InvalidArgumentException $e) {
            throw new \Shmandalf\Excelentor\Exceptions\CastException(
                $e->getMessage(),
                $propertyName,
                $type,
                $value,
                $rowIndex
            );
        } catch (\Throwable $e) {
            throw new \Shmandalf\Excelentor\Exceptions\CastException(
                sprintf('Unexpected error: %s', $e->getMessage()),
                $propertyName,
                $type,
                $value,
                $rowIndex
            );
        }
    }

    private function parseCasterConfigs(ReflectionClass $reflectionClass): void
    {
        // Получаем все атрибуты CasterConfig
        $configs = $reflectionClass->getAttributes(CasterConfig::class);

        foreach ($configs as $configAttr) {
            /** @var CasterConfig $config */
            $config = $configAttr->newInstance();

            // Сохраняем конфигурацию
            foreach ($config->config as $key => $definition) {
                $this->casterConfigs[$key] = $definition;
            }
        }
    }

    /**
     * Register a caster from CasterConfig definition
     *
     * @param string $key Type name (ClassName::class) or alias string
     * @param string|array $definition Caster class name or [className, ...args]
     */
    private function registerCasterFromConfig(string $key, string|array $definition): void
    {
        if (is_string($definition)) {
            // Simple mapping: 'alias' => CasterClass::class
            $casterClass = $definition;
            $args = [];
        } else {
            // Mapping with arguments: 'alias' => [CasterClass::class, arg1, arg2]
            $casterClass = $definition[0];
            $args = array_slice($definition, 1);
        }

        // Create caster instance with arguments
        $caster = new $casterClass(...$args);

        // Store in config-based registry
        $this->configBasedCasterRegistry[$key] = $caster;
    }

    /**
     * Resolve caster for a property based on Column attribute and property type
     *
     * Priority:
     * 1. Explicit caster alias in Column::caster
     * 2. Property type mapping from CasterConfig
     * 3. Already registered casters (from withCast())
     *
     * @return CasterInterface|null Resolved caster or null if not found
     */
    private function resolveCasterForProperty(ReflectionProperty $prop, Column $column): ?CasterInterface
    {
        // Check explicit caster alias from Column attribute
        if ($column->caster !== null) {
            return $this->getCasterByAlias($column->caster);
        }

        $propertyType = $prop->getType()->getName();

        // Otherwise by property type
        $propertyType = $prop->getType()->getName();

        return $this->getCasterByType($propertyType);
    }

    private function getCasterByAlias(string $alias): ?CasterInterface
    {
        if (!isset($this->casterConfigs[$alias])) {
            return null;
        }

        return $this->createCasterFromDefinition($this->casterConfigs[$alias]);
    }

    private function getCasterByType(string $type): ?CasterInterface
    {
        // Сначала ищем в casterConfigs
        if (isset($this->casterConfigs[$type])) {
            return $this->createCasterFromDefinition($this->casterConfigs[$type]);
        }

        // Потом в основном registry (от withCast())
        if (isset($this->casterRegistry[$type])) {
            return $this->casterRegistry[$type];
        }

        return null;
    }

    private function createCasterFromDefinition(string|array $definition): CasterInterface
    {
        if (is_string($definition)) {
            // Simple class name: 'alias' => CasterClass::class
            return new $definition();
        }

        // With arguments: 'alias' => [CasterClass::class, arg1, arg2, ...]
        $casterClass = $definition[0];
        $args = array_slice($definition, 1);

        return new $casterClass(...$args);
    }
}
