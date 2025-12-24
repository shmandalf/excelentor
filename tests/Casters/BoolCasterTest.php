<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Tests\Casters;

use PHPUnit\Framework\TestCase;
use Shmandalf\Excelentor\Casters\BoolCaster;
use InvalidArgumentException;

class BoolCasterTest extends TestCase
{
    private BoolCaster $caster;

    protected function setUp(): void
    {
        $this->caster = new BoolCaster();
    }

    public function testConvertsBoolean(): void
    {
        $this->assertTrue($this->caster->cast(true));
        $this->assertFalse($this->caster->cast(false));
    }

    public function testConvertsInteger(): void
    {
        $this->assertTrue($this->caster->cast(1));
        $this->assertTrue($this->caster->cast(42));
        $this->assertTrue($this->caster->cast(-1));

        $this->assertFalse($this->caster->cast(0));
    }

    public function testConvertsFloat(): void
    {
        $this->assertTrue($this->caster->cast(1.5));
        $this->assertTrue($this->caster->cast(0.1));
        $this->assertTrue($this->caster->cast(-0.1));

        $this->assertFalse($this->caster->cast(0.0));
    }

    public function testConvertsStringTrueValues(): void
    {
        $this->assertTrue($this->caster->cast('true'));
        $this->assertTrue($this->caster->cast('TRUE'));
        $this->assertTrue($this->caster->cast('yes'));
        $this->assertTrue($this->caster->cast('1'));
        $this->assertTrue($this->caster->cast('да'));
        $this->assertTrue($this->caster->cast('+'));
        $this->assertTrue($this->caster->cast('on'));
        $this->assertTrue($this->caster->cast('active'));
    }

    public function testConvertsStringFalseValues(): void
    {
        $this->assertFalse($this->caster->cast('false'));
        $this->assertFalse($this->caster->cast('FALSE'));
        $this->assertFalse($this->caster->cast('no'));
        $this->assertFalse($this->caster->cast('0'));
        $this->assertFalse($this->caster->cast('нет'));
        $this->assertFalse($this->caster->cast('-'));
        $this->assertFalse($this->caster->cast('off'));
        $this->assertFalse($this->caster->cast('inactive'));
        $this->assertFalse($this->caster->cast(''));
        $this->assertFalse($this->caster->cast('   '));
    }

    public function testConvertsNull(): void
    {
        $this->assertFalse($this->caster->cast(null));
    }

    public function testConvertsNumericStrings(): void
    {
        $this->assertTrue($this->caster->cast('2'));
        $this->assertTrue($this->caster->cast('3.14'));
        $this->assertTrue($this->caster->cast('-5'));

        $this->assertFalse($this->caster->cast('0'));
        $this->assertFalse($this->caster->cast('0.0'));
        $this->assertFalse($this->caster->cast('000'));
    }

    public function testThrowsOnUnrecognizedStringInStrictMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot interpret string "maybe" as boolean');

        $this->caster->cast('maybe');
    }

    public function testReturnsTrueForUnrecognizedStringInNonStrictMode(): void
    {
        $caster = new BoolCaster(strict: false);

        $this->assertTrue($caster->cast('maybe'));
        $this->assertTrue($caster->cast('unknown'));
        $this->assertTrue($caster->cast('whatever'));
    }

    public function testCustomTrueFalseValues(): void
    {
        $caster = new BoolCaster(
            trueValues: ['y', 'enable', 'ok'],
            falseValues: ['n', 'disable', 'fail']
        );

        $this->assertTrue($caster->cast('y'));
        $this->assertTrue($caster->cast('enable'));
        $this->assertTrue($caster->cast('ok'));

        $this->assertFalse($caster->cast('n'));
        $this->assertFalse($caster->cast('disable'));
        $this->assertFalse($caster->cast('fail'));

        // Стандартные значения больше не работают
        $this->expectException(InvalidArgumentException::class);
        $caster->cast('true');
    }

    public function testConvertsViaStringCaster(): void
    {
        // Объект с __toString()
        $object = new class {
            public function __toString(): string
            {
                return 'yes';
            }
        };

        $this->assertTrue($this->caster->cast($object));

        // Объект с числовым значением
        $numericObject = new class {
            public function __toString(): string
            {
                return '1';
            }
        };

        $this->assertTrue($this->caster->cast($numericObject));
    }

    public function testCaseInsensitiveComparison(): void
    {
        $this->assertTrue($this->caster->cast('True'));
        $this->assertTrue($this->caster->cast('TRUE'));
        $this->assertTrue($this->caster->cast('tRuE'));

        $this->assertFalse($this->caster->cast('False'));
        $this->assertFalse($this->caster->cast('FALSE'));
        $this->assertFalse($this->caster->cast('fAlSe'));
    }

    public function testWhitespaceHandling(): void
    {
        $this->assertTrue($this->caster->cast('  true  '));
        $this->assertTrue($this->caster->cast('  yes  '));
        $this->assertTrue($this->caster->cast('  1  '));

        $this->assertFalse($this->caster->cast('  false  '));
        $this->assertFalse($this->caster->cast('  no  '));
        $this->assertFalse($this->caster->cast('  0  '));
    }
}
