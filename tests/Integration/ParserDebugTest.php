<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Shmandalf\Excelentor\Attributes\{Column, Header, NoHeader};
use Shmandalf\Excelentor\Parser;
use Shmandalf\Excelentor\ValidatorFactory;

/**
 * 🔧 Debug Test DTOs (all classes outside methods)
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

#[Header(columns: [0 => 'name', 1 => 'age'], rows: 1)]
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

#[NoHeader(columns: [0 => 'email'])]
class EmailDebugTestDTO
{
    #[Column(rule: 'required|email')]
    public string $email;
}

#[NoHeader(columns: [0 => 'number'])]
class IntDebugTestDTO
{
    #[Column]
    public int $number;
}

/**
 * 🔧 Debug Test: Base Parser check
 */
class ParserDebugTest extends TestCase
{
    private ValidatorFactory $validatorFactory;

    protected function setUp(): void
    {
        $this->validatorFactory = new ValidatorFactory();
    }

    /**
     * Simplest test to make sure the Parser is working
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
     * Simple test for the String type
     */
    public function testStringParsingOnly(): void
    {
        $parser = new Parser(StringOnlyDebugTestDTO::class, $this->validatorFactory);
        $rows = [['John']];

        /** @var array $results */
        $results = iterator_to_array($parser->parse($rows));

        $this->assertCount(1, $results);
        $this->assertInstanceOf(StringOnlyDebugTestDTO::class, $results[0]);
        $this->assertSame('John', $results[0]->name);
    }

    /**
     * Header test (making sure the header is skipped)
     */
    public function testWithHeader(): void
    {
        $parser = new Parser(HeaderDebugTestDTO::class, $this->validatorFactory);
        $rows = [
            ['Name', 'Age'],    // header - skipped
            ['John', '30'],     // Row index 0
            ['Jane', '25'],     // Row index 1
        ];

        $results = $parser->parse($rows)->toArray();

        // Results have keys 1 and 2, not 0 and 1!
        $this->assertCount(2, $results);
        $this->assertArrayHasKey(0, $results, 'Should have key 0 (first data row)');
        $this->assertArrayHasKey(1, $results, 'Should have key 1 (second data row)');

        $this->assertSame('John', $results[0]->name);
        $this->assertSame('Jane', $results[1]->name);
    }

    /**
     * Nullable value test
     */
    public function testNullableField(): void
    {
        $parser = new Parser(NullableDebugTestDTO::class, $this->validatorFactory);

        // Test 1: with optional value
        $rows1 = [['John', 'value']];
        $results1 = $parser->parse($rows1)->toArray();
        $this->assertSame('value', $results1[0]->optional);

        // Test 2: without optional value (empty string)
        $rows2 = [['Jane', '']];
        $results2 = $parser->parse($rows2)->toArray();
        $this->assertNull($results2[0]->optional);
    }

    /**
     * Test with validation
     */
    public function testValidation(): void
    {
        $parser = new Parser(EmailDebugTestDTO::class, $this->validatorFactory);

        // Valid email
        $validRows = [['test@example.com']];
        $results = $parser->parse($validRows)->toArray();
        $this->assertCount(1, $results);
        $this->assertSame('test@example.com', $results[0]->email);

        // Invalid email - an exception must be thrown
        $invalidRows = [['not-an-email']];

        $exceptionThrown = false;

        $results = iterator_to_array($parser->parse($invalidRows));

        $this->assertCount(0, $results, 'Should skip row with invalid email');
    }

    /**
     * Test with exception when casting
     */
    public function testCastingException(): void
    {
        $parser = new Parser(IntDebugTestDTO::class, $this->validatorFactory);

        // Invalid number
        $rows = [['not-a-number']];

        $exceptionThrown = false;

        $results = iterator_to_array($parser->parse($rows));

        $this->assertCount(0, $results, 'Should skip row with invalid number');
    }
}
