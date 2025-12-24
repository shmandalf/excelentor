<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Shmandalf\Excelentor\Parser;
use Shmandalf\Excelentor\ValidatorFactory;
use Shmandalf\Excelentor\Attributes\{Header, Column, NoHeader};
use Carbon\Carbon;

/**
 * 🔧 Debug Test DTOs (все классы ВНЕ методов)
 */

#[NoHeader(columns: [0 => 'name', 1 => 'age'])]
class SimpleDebugTestDTO
{
    #[Column]
    public string $name;

    #[Column]
    public int $age;
}

#[NoHeader(columns: [0 => 'name'])]
class StringOnlyDebugTestDTO
{
    #[Column]
    public string $name;
}

#[Header(columns: [0 => 'name', 1 => 'age'], rows: 1, stopOnFirstFailure: true)]
class HeaderDebugTestDTO
{
    #[Column]
    public string $name;

    #[Column]
    public int $age;
}

#[NoHeader(columns: [0 => 'name', 1 => 'optional'])]
class NullableDebugTestDTO
{
    #[Column]
    public string $name;

    #[Column]
    public ?string $optional;
}

#[NoHeader(columns: [0 => 'email'], stopOnFirstFailure: true)]
class EmailDebugTestDTO
{
    #[Column(rule: 'required|email')]
    public string $email;
}

#[NoHeader(columns: [0 => 'number'], stopOnFirstFailure: true)]
class IntDebugTestDTO
{
    #[Column]
    public int $number;
}

/**
 * 🔧 Debug Test: Базовая проверка работы Parser
 */
class ParserDebugTest extends TestCase
{
    private ValidatorFactory $validatorFactory;

    protected function setUp(): void
    {
        $this->validatorFactory = new ValidatorFactory();
    }

    /**
     * Простейший тест чтобы проверить что парсер вообще работает
     */
    public function testBasicParsingWorks(): void
    {
        $parser = new Parser(SimpleDebugTestDTO::class, $this->validatorFactory);

        $rows = [['John', '30']];

        $results = array_values(iterator_to_array($parser->parse($rows)));

        $this->assertCount(1, $results, 'Should parse 1 data row');
        $this->assertInstanceOf(SimpleDebugTestDTO::class, $results[0]);
        $this->assertSame('John', $results[0]->name);
        $this->assertSame(30, $results[0]->age);
    }

    /**
     * Тест только для String типа (самый простой)
     */
    public function testStringParsingOnly(): void
    {
        $parser = new Parser(StringOnlyDebugTestDTO::class, $this->validatorFactory);
        $rows = [['John']];

        $results = iterator_to_array($parser->parse($rows));

        $this->assertCount(1, $results);
        $this->assertInstanceOf(StringOnlyDebugTestDTO::class, $results[0]);
        $this->assertSame('John', $results[0]->name);
    }

    /**
     * Тест с Header (проверяем что заголовок пропускается)
     */
    public function testWithHeader(): void
    {
        $parser = new Parser(HeaderDebugTestDTO::class, $this->validatorFactory);
        $rows = [
            ['Name', 'Age'],    // Row index 0 (header - skipped)
            ['John', '30'],     // Row index 1
            ['Jane', '25'],     // Row index 2
        ];

        $results = iterator_to_array($parser->parse($rows));

        // Результаты имеют ключи 1 и 2, не 0 и 1!
        $this->assertCount(2, $results);
        $this->assertArrayHasKey(1, $results, 'Should have key 1 (first data row)');
        $this->assertArrayHasKey(2, $results, 'Should have key 2 (second data row)');

        $this->assertSame('John', $results[1]->name);
        $this->assertSame('Jane', $results[2]->name);
    }

    /**
     * Тест с nullable полем
     */
    public function testNullableField(): void
    {
        $parser = new Parser(NullableDebugTestDTO::class, $this->validatorFactory);

        // Тест 1: с optional значением
        $rows1 = [['John', 'value']];
        $results1 = iterator_to_array($parser->parse($rows1));
        $this->assertSame('value', $results1[0]->optional);

        // Тест 2: без optional значения (пустая строка)
        $rows2 = [['Jane', '']];
        $results2 = iterator_to_array($parser->parse($rows2));
        $this->assertNull($results2[0]->optional);
    }

    /**
     * Тест с валидацией
     */
    public function testValidation(): void
    {
        $parser = new Parser(EmailDebugTestDTO::class, $this->validatorFactory);

        // Валидный email
        $validRows = [['test@example.com']];
        $results = iterator_to_array($parser->parse($validRows));
        $this->assertCount(1, $results);
        $this->assertSame('test@example.com', $results[0]->email);

        // Невалидный email - должно бросить исключение
        $invalidRows = [['not-an-email']];

        $exceptionThrown = false;
        try {
            iterator_to_array($parser->parse($invalidRows));
        } catch (\Shmandalf\Excelentor\Exceptions\ValidationException $e) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown, 'Should throw ValidationException for invalid email');
    }

    /**
     * Тест с исключением при кастинге
     */
    public function testCastingException(): void
    {
        $parser = new Parser(IntDebugTestDTO::class, $this->validatorFactory);

        // Невалидное число
        $rows = [['not-a-number']];

        $exceptionThrown = false;
        try {
            iterator_to_array($parser->parse($rows));
        } catch (\Shmandalf\Excelentor\Exceptions\ValidationException $e) {
            $exceptionThrown = true;
        } catch (\Throwable $e) {
        }

        $this->assertTrue($exceptionThrown, 'Should throw exception for invalid int');
    }
}
