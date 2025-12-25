<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Tests\Casters;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Shmandalf\Excelentor\Casters\FloatCaster;

class FloatCasterTest extends TestCase
{
    private FloatCaster $caster;

    protected function setUp(): void
    {
        $this->caster = new FloatCaster();
    }

    public function testConvertsFloat(): void
    {
        $this->assertSame(3.14, $this->caster->cast(3.14));
        $this->assertSame(0.0, $this->caster->cast(0.0));
        $this->assertSame(-10.5, $this->caster->cast(-10.5));
    }

    public function testConvertsInteger(): void
    {
        $this->assertSame(42.0, $this->caster->cast(42));
        $this->assertSame(0.0, $this->caster->cast(0));
        $this->assertSame(-10.0, $this->caster->cast(-10));
    }

    public function testConvertsBoolean(): void
    {
        $this->assertSame(1.0, $this->caster->cast(true));
        $this->assertSame(0.0, $this->caster->cast(false));
    }

    public function testConvertsNumericString(): void
    {
        $this->assertSame(3.14, $this->caster->cast('3.14'));
        $this->assertSame(-10.5, $this->caster->cast('-10.5'));
        $this->assertSame(0.0, $this->caster->cast('0'));
    }

    public function testConvertsStringWithCommaDecimal(): void
    {
        // For european format "1.234,56" need to provide both separators
        $caster = new FloatCaster(
            decimalSeparator: ',',
            thousandsSeparator: '.'  // dot as thousands separator
        );

        $this->assertSame(3.14, $caster->cast('3,14'));
        $this->assertSame(1234.56, $caster->cast('1.234,56'));
        $this->assertSame(1234567.89, $caster->cast('1.234.567,89'));
    }

    public function testConvertsStringWithDefaultFormat(): void
    {
        // By default: US format
        $this->assertSame(1234.56, $this->caster->cast('1,234.56'));
        $this->assertSame(1234567.89, $this->caster->cast('1,234,567.89'));
    }

    public function testConvertsStringWithThousands(): void
    {
        $this->assertSame(1000.5, $this->caster->cast('1,000.5'));
        $this->assertSame(1000.5, $this->caster->cast('1 000.5'));
        $this->assertSame(1000000.0, $this->caster->cast('1,000,000'));
    }

    public function testConvertsScientificNotation(): void
    {
        $this->assertSame(1230.0, $this->caster->cast('1.23e3'));
        $this->assertSame(0.00123, $this->caster->cast('1.23e-3'));
        $this->assertSame(-1230.0, $this->caster->cast('-1.23e3'));
    }

    public function testThrowsOnNonNumericString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a valid float');

        $this->caster->cast('not a number');
    }

    public function testConvertsNullWhenAllowed(): void
    {
        $caster = new FloatCaster(allowNull: true);
        $this->assertSame(0.0, $caster->cast(null));
    }

    public function testThrowsOnNullWhenNotAllowed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot convert null to float');

        $this->caster->cast(null);
    }

    public function testHandlesSpecialValues(): void
    {
        // NaN
        $caster = new FloatCaster(allowNaN: true);
        $this->assertTrue(is_nan($caster->cast(NAN)));
        $this->assertTrue(is_nan($caster->cast('nan')));

        // Infinity
        $caster = new FloatCaster(allowInfinity: true);
        $this->assertSame(INF, $caster->cast(INF));
        $this->assertSame(INF, $caster->cast('inf'));
        $this->assertSame(-INF, $caster->cast('-inf'));
    }

    public function testThrowsOnSpecialValuesWhenNotAllowed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('NaN values are not allowed');

        $this->caster->cast(NAN);
    }

    public function testValidatesMinValue(): void
    {
        $caster = new FloatCaster(min: 0.0);

        $this->assertSame(0.0, $caster->cast(0.0));
        $this->assertSame(10.5, $caster->cast(10.5));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is less than minimum');

        $caster->cast(-5.0);
    }

    public function testValidatesMaxValue(): void
    {
        $caster = new FloatCaster(max: 100.0);

        $this->assertSame(100.0, $caster->cast(100.0));
        $this->assertSame(50.5, $caster->cast(50.5));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is greater than maximum');

        $caster->cast(150.0);
    }

    public function testAppliesFormatRounding(): void
    {
        // Use assertEqualsWithDelta for float comparisons
        $this->assertEqualsWithDelta(3.14, $this->caster->cast(3.14159, '2'), 0.0001);
        $this->assertEqualsWithDelta(3.0, $this->caster->cast(3.14159, '0'), 0.0001);
        $this->assertEqualsWithDelta(3.142, $this->caster->cast(3.14159, '3'), 0.0001);
        $this->assertEqualsWithDelta(1000.0, $this->caster->cast(999.999, '0'), 0.0001);

        // String input
        $this->assertEqualsWithDelta(3.14, $this->caster->cast('3.14159', '2'), 0.0001);

        // Integer input with decimals - 42 with format '2' is still considered 42.0 as float
        $this->assertEqualsWithDelta(42.0, $this->caster->cast(42, '2'), 0.0001);
    }

    public function testFloatPrecision(): void
    {
        // Rounding must be working correctly
        $this->assertEqualsWithDelta(2.68, $this->caster->cast(2.675, '2'), 0.0001); // round(2.675, 2) = 2.68
        $this->assertEqualsWithDelta(2.67, $this->caster->cast(2.674, '2'), 0.0001); // round(2.674, 2) = 2.67
    }

    public function testConvertsViaStringCaster(): void
    {
        $object = new class () {
            public function __toString(): string
            {
                return '42.5';
            }
        };

        $this->assertSame(42.5, $this->caster->cast($object));
    }
}
