<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Tests\Casters;

use PHPUnit\Framework\TestCase;
use Shmandalf\Excelentor\Casters\StringCaster;
use InvalidArgumentException;

class StringCasterTest extends TestCase
{
    private StringCaster $caster;

    protected function setUp(): void
    {
        $this->caster = new StringCaster();
    }

    public function testConvertsString(): void
    {
        $this->assertSame('hello', $this->caster->cast('hello'));
        $this->assertSame('hello', $this->caster->cast('  hello  '));
    }

    public function testConvertsInteger(): void
    {
        $this->assertSame('42', $this->caster->cast(42));
        $this->assertSame('0', $this->caster->cast(0));
        $this->assertSame('-10', $this->caster->cast(-10));
    }

    public function testConvertsFloat(): void
    {
        $this->assertSame('3.14', $this->caster->cast(3.14));
        $this->assertSame('0.5', $this->caster->cast(0.5));
    }

    public function testConvertsFloatWithSimpleFormat(): void
    {
        $this->assertSame('3.14', $this->caster->cast(3.14, '2'));
        $this->assertSame('3.140', $this->caster->cast(3.14, '3'));
        $this->assertSame('1000.50', $this->caster->cast(1000.5, '2'));
        $this->assertSame('1001', $this->caster->cast(1000.5, '0')); // Rounding up
        $this->assertSame('1000', $this->caster->cast(1000.4, '0')); // Rounding down
        $this->assertSame('1234567.89', $this->caster->cast(1234567.89, '2'));
    }

    public function testRoundingExamples(): void
    {
        $this->assertSame('1', $this->caster->cast(0.5, '0'));
        $this->assertSame('0', $this->caster->cast(0.4, '0'));
        $this->assertSame('2', $this->caster->cast(1.5, '0'));
        $this->assertSame('1.55', $this->caster->cast(1.545, '2'));
        $this->assertSame('1.55', $this->caster->cast(1.545, '2')); // number_format for rounding
    }

    public function testIgnoresNonNumericFormat(): void
    {
        // Non-numeric format is ignored
        $this->assertSame('3.14', $this->caster->cast(3.14, 'invalid'));
    }

    public function testConvertsBoolean(): void
    {
        $this->assertSame('true', $this->caster->cast(true));
        $this->assertSame('false', $this->caster->cast(false));
    }

    public function testConvertsNull(): void
    {
        $this->assertSame('', $this->caster->cast(null));
    }

    public function testThrowsOnNullWhenNotAllowed(): void
    {
        $caster = new StringCaster(allowNull: false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot convert null to string when null is not allowed');

        $caster->cast(null);
    }

    public function testConvertsObjectWithToString(): void
    {
        $object = new class {
            public function __toString(): string
            {
                return 'object string';
            }
        };

        $this->assertSame('object string', $this->caster->cast($object));
    }

    public function testThrowsOnArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot convert array to string');

        $this->caster->cast(['foo', 'bar']);
    }

    public function testThrowsOnResource(): void
    {
        $resource = fopen('php://memory', 'r');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot convert resource to string');

        try {
            $this->caster->cast($resource);
        } finally {
            fclose($resource);
        }
    }

    public function testWithoutTrimming(): void
    {
        $caster = new StringCaster(trim: false);
        $this->assertSame('  hello  ', $caster->cast('  hello  '));
    }
}
