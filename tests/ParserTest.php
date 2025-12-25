<?php

namespace Shmandalf\Excelentor\Tests;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Shmandalf\Excelentor\Parser;
use Shmandalf\Excelentor\Tests\Fixtures\ValidFixture;
use Shmandalf\Excelentor\ValidatorFactory;

class ParserTest extends TestCase
{
    private ValidatorFactory $validatorFactory;

    protected function setUp(): void
    {
        $this->validatorFactory = new ValidatorFactory('ru');
    }

    public function test_parsing_success()
    {
        $parser = new Parser(ValidFixture::class, $this->validatorFactory);

        $rows = [
            ['some@there.com', 1, '31.10.1977', '', 123.23]
        ];

        $result = iterator_to_array($parser->parse($rows));

        // Assert
        $item = $result[0];

        // All properties exist
        $this->assertObjectHasProperty('email', $item);
        $this->assertObjectHasProperty('int', $item);
        $this->assertObjectHasProperty('date', $item);
        $this->assertObjectHasProperty('string', $item);
        $this->assertObjectHasProperty('float', $item);

        // Validating property types
        $reflection = new ReflectionClass($item);

        $emailProp = $reflection->getProperty('email');
        $this->assertSame('string', $emailProp->getType()->getName());
        $this->assertSame('some@there.com', $emailProp->getValue($item));

        $intProp = $reflection->getProperty('int');
        $this->assertSame('int', $intProp->getType()->getName());

        $dateProp = $reflection->getProperty('date');
        $this->assertSame(Carbon::class, $dateProp->getType()->getName());
        $this->assertTrue($dateProp->getType()->allowsNull());

        $stringProp = $reflection->getProperty('string');
        $this->assertSame('string', $stringProp->getType()->getName());

        $floatProp = $reflection->getProperty('float');
        $this->assertSame('float', $floatProp->getType()->getName());
        $this->assertTrue($floatProp->getType()->allowsNull());
    }
}
