<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Tests\Casters;

use PHPUnit\Framework\TestCase;
use Shmandalf\Excelentor\Casters\IntCaster;
use InvalidArgumentException;

class IntCasterTest extends TestCase
{
    private IntCaster $caster;

    protected function setUp(): void
    {
        $this->caster = new IntCaster();
    }

    public function testConvertsInteger(): void
    {
        $this->assertSame(42, $this->caster->cast(42));
        $this->assertSame(0, $this->caster->cast(0));
        $this->assertSame(-10, $this->caster->cast(-10));
    }

    public function testConvertsBoolean(): void
    {
        $this->assertSame(1, $this->caster->cast(true));
        $this->assertSame(0, $this->caster->cast(false));
    }

    public function testConvertsIntegerFloat(): void
    {
        $this->assertSame(10, $this->caster->cast(10.0));
        $this->assertSame(0, $this->caster->cast(0.0));
        $this->assertSame(-5, $this->caster->cast(-5.0));
    }

    public function testThrowsOnDecimalFloat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has decimal part');

        $this->caster->cast(10.5);
    }

    public function testConvertsNumericString(): void
    {
        $this->assertSame(42, $this->caster->cast('42'));
        $this->assertSame(-10, $this->caster->cast('-10'));
        $this->assertSame(0, $this->caster->cast('0'));
    }

    public function testConvertsStringWithThousands(): void
    {
        $this->assertSame(1000, $this->caster->cast('1,000'));
        $this->assertSame(1000, $this->caster->cast('1 000'));
        $this->assertSame(1000, $this->caster->cast('1.000'));
        $this->assertSame(1000000, $this->caster->cast('1,000,000'));
    }

    public function testThrowsOnNonNumericString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // The message will be 'String value "not a number" is not numeric'
        $this->expectExceptionMessage('is not numeric');

        $this->caster->cast('not a number');
    }

    public function testThrowsOnDecimalString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has decimal part');

        $this->caster->cast('10.5');
    }

    public function testThrowsOnOverflow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('integer overflow');

        // PHP_INT_MAX + 1 as string
        $hugeNumber = (string) (PHP_INT_MAX) . '0';
        $this->caster->cast($hugeNumber);
    }

    public function testConvertsNullWhenAllowed(): void
    {
        $caster = new IntCaster(allowNull: true);
        $this->assertSame(0, $caster->cast(null));
    }

    public function testThrowsOnNullWhenNotAllowed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot convert null to integer');

        $this->caster->cast(null);
    }

    public function testValidatesMinValue(): void
    {
        $caster = new IntCaster(min: 0);

        $this->assertSame(0, $caster->cast(0));
        $this->assertSame(10, $caster->cast(10));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is less than minimum');

        $caster->cast(-5);
    }

    public function testValidatesMaxValue(): void
    {
        $caster = new IntCaster(max: 100);

        $this->assertSame(100, $caster->cast(100));
        $this->assertSame(50, $caster->cast(50));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is greater than maximum');

        $caster->cast(150);
    }

    public function testConvertsViaStringCaster(): void
    {
        // Object with __toString()
        $object = new class {
            public function __toString(): string
            {
                return '42';
            }
        };

        $this->assertSame(42, $this->caster->cast($object));
    }
}
