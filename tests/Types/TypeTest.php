<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Tests\Types;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Shmandalf\Excelentor\Types\Type;

class TypeTest extends TestCase
{
    /**
     * @dataProvider primitiveTypeProvider
     */
    public function testPrimitiveTypesResolveCorrectly(Type $type, array $expected): void
    {
        $this->assertEquals($expected, $type->resolve());
    }

    /**
     * @dataProvider classTypeProvider
     */
    public function testClassTypesResolveToThemselves(Type $type, array $expected): void
    {
        $this->assertEquals($expected, $type->resolve());
    }

    public function testDateAliasResolvesToAllDateTypes(): void
    {
        $expected = [
            Carbon::class,
            \DateTime::class,
            \DateTimeImmutable::class,
        ];

        $result = Type::DATE->resolve();

        $this->assertEquals($expected, $result);
        $this->assertCount(3, $result);
    }

    public function testNumberAliasResolvesToAllNumberTypes(): void
    {
        $expected = ['int', 'integer', 'float', 'double'];

        $result = Type::NUMBER->resolve();

        $this->assertEquals($expected, $result);
        $this->assertCount(4, $result);
    }

    public function testDateTypesHelperReturnsCorrectCases(): void
    {
        $dateTypes = Type::dateTypes();

        $this->assertCount(3, $dateTypes);
        $this->assertContains(Type::CARBON, $dateTypes);
        $this->assertContains(Type::DATETIME, $dateTypes);
        $this->assertContains(Type::DATETIME_IMMUTABLE, $dateTypes);
        $this->assertNotContains(Type::DATE, $dateTypes); // DATE is alias, not concrete type
    }

    public function testNumberTypesHelperReturnsCorrectCases(): void
    {
        $numberTypes = Type::numberTypes();

        $this->assertCount(4, $numberTypes);
        $this->assertContains(Type::INT, $numberTypes);
        $this->assertContains(Type::INTEGER, $numberTypes);
        $this->assertContains(Type::FLOAT, $numberTypes);
        $this->assertContains(Type::DOUBLE, $numberTypes);
        $this->assertNotContains(Type::NUMBER, $numberTypes); // NUMBER is alias
    }

    public function testBoolTypesHelperReturnsCorrectCases(): void
    {
        $boolTypes = Type::boolTypes();

        $this->assertCount(2, $boolTypes);
        $this->assertContains(Type::BOOL, $boolTypes);
        $this->assertContains(Type::BOOLEAN, $boolTypes);
    }

    public function testStringTypesHelperReturnsCorrectCases(): void
    {
        $stringTypes = Type::stringTypes();

        $this->assertCount(1, $stringTypes);
        $this->assertContains(Type::STRING, $stringTypes);
    }

    public function testIsPrimitiveForDifferentTypes(): void
    {
        // Primitive types
        $this->assertTrue(Type::INT->isPrimitive());
        $this->assertTrue(Type::INTEGER->isPrimitive());
        $this->assertTrue(Type::FLOAT->isPrimitive());
        $this->assertTrue(Type::DOUBLE->isPrimitive());
        $this->assertTrue(Type::BOOL->isPrimitive());
        $this->assertTrue(Type::BOOLEAN->isPrimitive());
        $this->assertTrue(Type::STRING->isPrimitive());

        // Non-primitive types
        $this->assertFalse(Type::CARBON->isPrimitive());
        $this->assertFalse(Type::DATETIME->isPrimitive());
        $this->assertFalse(Type::DATETIME_IMMUTABLE->isPrimitive());
        $this->assertFalse(Type::DATE->isPrimitive());
        $this->assertFalse(Type::NUMBER->isPrimitive());
    }

    public function testIsDateForDifferentTypes(): void
    {
        // Date types
        $this->assertTrue(Type::DATE->isDate());
        $this->assertTrue(Type::CARBON->isDate());
        $this->assertTrue(Type::DATETIME->isDate());
        $this->assertTrue(Type::DATETIME_IMMUTABLE->isDate());

        // Non-date types
        $this->assertFalse(Type::INT->isDate());
        $this->assertFalse(Type::STRING->isDate());
        $this->assertFalse(Type::BOOL->isDate());
        $this->assertFalse(Type::FLOAT->isDate());
        $this->assertFalse(Type::NUMBER->isDate());
    }

    public function testIsNumberForDifferentTypes(): void
    {
        // Number types
        $this->assertTrue(Type::INT->isNumber());
        $this->assertTrue(Type::INTEGER->isNumber());
        $this->assertTrue(Type::FLOAT->isNumber());
        $this->assertTrue(Type::DOUBLE->isNumber());
        $this->assertTrue(Type::NUMBER->isNumber());

        // Non-number types
        $this->assertFalse(Type::STRING->isNumber());
        $this->assertFalse(Type::BOOL->isNumber());
        $this->assertFalse(Type::DATE->isNumber());
        $this->assertFalse(Type::CARBON->isNumber());
    }

    public function testTryFromStringWithExactMatches(): void
    {
        $testCases = [
            'int' => Type::INT,
            'integer' => Type::INTEGER,
            'float' => Type::FLOAT,
            'double' => Type::DOUBLE,
            'bool' => Type::BOOL,
            'boolean' => Type::BOOLEAN,
            'string' => Type::STRING,
            'date' => Type::DATE,
            'number' => Type::NUMBER,
            Carbon::class => Type::CARBON,
            \DateTime::class => Type::DATETIME,
            \DateTimeImmutable::class => Type::DATETIME_IMMUTABLE,
        ];

        foreach ($testCases as $input => $expected) {
            $result = Type::tryFromString($input);
            $this->assertSame(
                $expected,
                $result,
                sprintf('Failed for input "%s"', $input)
            );
        }
    }

    public function testTryFromStringWithCaseInsensitivePrimitives(): void
    {
        $testCases = [
            'INT' => Type::INT,
            'Integer' => Type::INTEGER,
            'FLOAT' => Type::FLOAT,
            'Double' => Type::DOUBLE,
            'BOOL' => Type::BOOL,
            'Boolean' => Type::BOOLEAN,
            'STRING' => Type::STRING,
        ];

        foreach ($testCases as $input => $expected) {
            $result = Type::tryFromString($input);
            $this->assertSame(
                $expected,
                $result,
                sprintf('Failed for case-insensitive input "%s"', $input)
            );
        }
    }

    public function testTryFromStringWithUnknownTypesReturnsNull(): void
    {
        $unknownTypes = [
            'money',
            'uuid',
            'email',
            'App\\Money',
            'CustomType',
            '',
            'unknown',
            123, // Will be cast to string '123'
        ];

        foreach ($unknownTypes as $input) {
            $result = Type::tryFromString((string) $input);
            $this->assertNull(
                $result,
                sprintf(
                    'Expected null for "%s", got %s',
                    $input,
                    $result ? $result->value : 'null'
                )
            );
        }
    }

    public function testIsKnownTypeReturnsCorrectResults(): void
    {
        // Known types
        $this->assertTrue(Type::isKnownType('int'));
        $this->assertTrue(Type::isKnownType('INT'));
        $this->assertTrue(Type::isKnownType('Integer'));
        $this->assertTrue(Type::isKnownType(Carbon::class));
        $this->assertTrue(Type::isKnownType('date'));
        $this->assertTrue(Type::isKnownType('number'));

        // Unknown types
        $this->assertFalse(Type::isKnownType('money'));
        $this->assertFalse(Type::isKnownType('App\\ValueObject\\Money'));
        $this->assertFalse(Type::isKnownType(''));
        $this->assertFalse(Type::isKnownType('unknown'));
    }

    public function testEnumValuesAreCorrect(): void
    {
        $this->assertSame('int', Type::INT->value);
        $this->assertSame('integer', Type::INTEGER->value);
        $this->assertSame('float', Type::FLOAT->value);
        $this->assertSame('double', Type::DOUBLE->value);
        $this->assertSame('bool', Type::BOOL->value);
        $this->assertSame('boolean', Type::BOOLEAN->value);
        $this->assertSame('string', Type::STRING->value);
        $this->assertSame('date', Type::DATE->value);
        $this->assertSame('number', Type::NUMBER->value);
        $this->assertSame(Carbon::class, Type::CARBON->value);
        $this->assertSame(\DateTime::class, Type::DATETIME->value);
        $this->assertSame(\DateTimeImmutable::class, Type::DATETIME_IMMUTABLE->value);
    }

    public function testAllCasesExist(): void
    {
        $cases = Type::cases();

        $this->assertCount(12, $cases);

        $expectedValues = [
            'int',
            'integer',
            'float',
            'double',
            'bool',
            'boolean',
            'string',
            'date',
            'number',
            Carbon::class,
            \DateTime::class,
            \DateTimeImmutable::class,
        ];

        foreach ($cases as $case) {
            $this->assertContains($case->value, $expectedValues);
        }
    }

    public function testTypeCanBeUsedInMatchStatement(): void
    {
        $testType = Type::INT;

        $result = match ($testType) {
            Type::INT, Type::INTEGER => 'integer',
            Type::FLOAT, Type::DOUBLE => 'float',
            Type::BOOL, Type::BOOLEAN => 'boolean',
            Type::STRING => 'string',
            Type::DATE => 'date',
            Type::NUMBER => 'number',
            default => 'other',
        };

        $this->assertSame('integer', $result);
    }

    public static function primitiveTypeProvider(): array
    {
        return [
            'int' => [Type::INT, ['int', 'integer']],
            'integer' => [Type::INTEGER, ['int', 'integer']],
            'float' => [Type::FLOAT, ['float', 'double']],
            'double' => [Type::DOUBLE, ['float', 'double']],
            'bool' => [Type::BOOL, ['bool', 'boolean']],
            'boolean' => [Type::BOOLEAN, ['bool', 'boolean']],
            'string' => [Type::STRING, ['string']],
        ];
    }

    public static function classTypeProvider(): array
    {
        return [
            'Carbon' => [Type::CARBON, [Carbon::class]],
            'DateTime' => [Type::DATETIME, [\DateTime::class]],
            'DateTimeImmutable' => [Type::DATETIME_IMMUTABLE, [\DateTimeImmutable::class]],
        ];
    }
}
