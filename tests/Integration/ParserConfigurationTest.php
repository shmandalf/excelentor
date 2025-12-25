<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Tests\Integration;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Shmandalf\Excelentor\Attributes\Column;
use Shmandalf\Excelentor\Attributes\NoHeader;
use Shmandalf\Excelentor\Contracts\CasterInterface;
use Shmandalf\Excelentor\Parser;
use Shmandalf\Excelentor\Types\Type;
use Shmandalf\Excelentor\ValidatorFactory;

class CustomValueObject
{
    public function __construct(private string $value)
    {
    }
    public function getValue(): string
    {
        return $this->value;
    }
}

#[NoHeader(columns: [0 => 'value'])]
class TestWithCastDTO
{
    #[Column()]
    public string $value;
}

#[NoHeader(columns: [0 => 'age'])]
class TestWithoutCastDTO
{
    #[Column]
    public int $age;
}

#[NoHeader(columns: [0 => 'number'])]
class TestOverrideDTO
{
    #[Column]
    public int $number;
}

#[NoHeader(columns: [0 => 'date'])]
class TestDateDTO
{
    #[Column(format: 'Y-m-d')]
    public Carbon $date;
}

#[NoHeader(columns: [0 => 'price'])]
class TestEuropeanDTO
{
    #[Column]
    public float $price;
}

#[NoHeader(columns: [0 => 'price'])]
class TestUSDTO
{
    #[Column]
    public float $price;
}

#[NoHeader(columns: [0 => 'value'])]
class TestResetDTO
{
    #[Column]
    public string $value;
}

#[NoHeader(columns: [0 => 'custom'])]
class TestCustomDTO
{
    #[Column]
    public CustomValueObject $custom;
}

/**
 * Tests for Parser configuration methods (withCast, withoutCast, etc.)
 */
class ParserConfigurationTest extends TestCase
{
    private ValidatorFactory $validatorFactory;

    protected function setUp(): void
    {
        $this->validatorFactory = new ValidatorFactory();
    }

    public function testWithCastMethodRegistersCustomCaster(): void
    {
        $parser = new Parser(TestWithCastDTO::class, $this->validatorFactory);

        // Custom caster that transforms values
        $customCaster = new class () implements CasterInterface {
            public function cast($value, ?string $format = null): mixed
            {
                return strtoupper($value);
            }
        };

        // Configure parser with custom caster
        $configuredParser = $parser->withCast($customCaster, Type::STRING);

        // Should be immutable (different instances)
        $this->assertNotSame($parser, $configuredParser);

        // Test parsing with custom caster
        $rows = [['hello']];
        $result = $configuredParser->parse($rows);
        $entities = $result->toArray();

        $this->assertCount(1, $entities);
        $this->assertSame('HELLO', $entities[0]->value);
    }

    public function testWithoutCastMethodRemovesCaster(): void
    {
        $parser = new Parser(TestWithoutCastDTO::class, $this->validatorFactory);

        // Remove int caster
        $configuredParser = $parser->withoutCast(Type::INT);

        // Should fail because no caster for int
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No casters registered');

        $configuredParser->parse([['25']]);
    }

    public function testWithCastOverridesExistingCaster(): void
    {
        $parser = new Parser(TestOverrideDTO::class, $this->validatorFactory);

        // Custom int caster that multiplies by 2
        $multiplyingCaster = new class () implements CasterInterface {
            public function cast($value, ?string $format = null): mixed
            {
                return (int)$value * 2;
            }
        };

        $configuredParser = $parser->withCast($multiplyingCaster, Type::INT);

        $rows = [['10']];
        $result = $configuredParser->parse($rows);
        $entities = $result->toArray();

        $this->assertCount(1, $entities);
        $this->assertSame(20, $entities[0]->number); // 10 * 2
    }

    public function testWithDateCasterMethod(): void
    {
        $parser = new Parser(TestDateDTO::class, $this->validatorFactory);

        // Create date caster with specific timezone
        $configuredParser = $parser->withDateCaster('Europe/Moscow');

        $rows = [['2023-12-25']];
        $result = $configuredParser->parse($rows);
        $entities = $result->toArray();

        $this->assertCount(1, $entities);
        $this->assertSame('2023-12-25', $entities[0]->date->format('Y-m-d'));
    }

    public function testWithEuropeanNumbersMethod(): void
    {
        $parser = new Parser(TestEuropeanDTO::class, $this->validatorFactory);

        $configuredParser = $parser->withEuropeanNumbers();

        // Test European format: 1 234,56
        $rows = [['1 234,56']];
        $result = $configuredParser->parse($rows);
        $entities = $result->toArray();

        $this->assertCount(1, $entities);
        $this->assertEqualsWithDelta(1234.56, $entities[0]->price, 0.01);
    }

    public function testWithUSNumbersMethod(): void
    {
        $parser = new Parser(TestUSDTO::class, $this->validatorFactory);

        $configuredParser = $parser->withUSNumbers();

        // Test US format: 1,234.56
        $rows = [['1,234.56']];
        $result = $configuredParser->parse($rows);
        $entities = $result->toArray();

        $this->assertCount(1, $entities);
        $this->assertEqualsWithDelta(1234.56, $entities[0]->price, 0.01);
    }

    public function testWithDefaultCastersResetsToDefaults(): void
    {
        $parser = new Parser(TestResetDTO::class, $this->validatorFactory);

        // First remove string caster
        $withoutCaster = $parser->withoutCast(Type::STRING);

        // Should fail without string caster
        $this->expectException(\RuntimeException::class);
        $withoutCaster->parse([['test']]);

        // Reset to defaults
        $resetParser = $withoutCaster->withDefaultCasters();

        // Should work again
        $result = $resetParser->parse([['test']]);
        $entities = $result->toArray();

        $this->assertCount(1, $entities);
        $this->assertSame('test', $entities[0]->value);
    }

    public function testWithCastAcceptsStringForCustomTypes(): void
    {
        $parser = new Parser(TestCustomDTO::class, $this->validatorFactory);

        // Custom caster for CustomValueObject
        $customCaster = new class () implements CasterInterface {
            public function cast($value, ?string $format = null): mixed
            {
                return new CustomValueObject('custom:' . $value);
            }
        };

        // Register with string (class name)
        $configuredParser = $parser->withCast($customCaster, CustomValueObject::class);

        $rows = [['test']];
        $result = $configuredParser->parse($rows);
        $entities = $result->toArray();

        $this->assertCount(1, $entities);
        $this->assertInstanceOf(CustomValueObject::class, $entities[0]->custom);
        $this->assertSame('custom:test', $entities[0]->custom->getValue());
    }

    public function testHasCasterForMethod(): void
    {
        $parser = new Parser(TestWithCastDTO::class, $this->validatorFactory);

        // By default should have string caster
        $this->assertTrue($parser->hasCasterFor(Type::STRING));
        $this->assertTrue($parser->hasCasterFor('string')); // String also works

        // Remove it
        $withoutCaster = $parser->withoutCast(Type::STRING);
        $this->assertFalse($withoutCaster->hasCasterFor(Type::STRING));

        // Add custom caster
        $customCaster = new class () implements CasterInterface {
            public function cast($value, ?string $format = null): mixed
            {
                return $value;
            }
        };

        $withCustom = $withoutCaster->withCast($customCaster, 'custom_type');
        $this->assertTrue($withCustom->hasCasterFor('custom_type'));
    }
}
