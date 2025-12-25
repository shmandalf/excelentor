<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Tests\Integration;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Shmandalf\Excelentor\Attributes\{Column, Header, NoHeader};
use Shmandalf\Excelentor\Parser;
use Shmandalf\Excelentor\ValidatorFactory;

/**
 * DTO classes for tests
 */

#[Header(columns: [
    0 => 'name',
    1 => 'age',
    2 => 'price',
    3 => 'active',
    4 => 'score',
    5 => 'created_at',
])]
class ProductImportTestDTO
{
    #[Column(rule: 'required|min:2')]
    public string $name;

    #[Column(rule: 'required|integer|min:0|max:150')]
    public int $age;

    #[Column(rule: 'required|numeric|min:0')]
    public float $price;

    #[Column]
    public bool $active;

    #[Column]
    public ?float $score;

    #[Column(format: 'Y-m-d')]
    public Carbon $created_at;

    public function getName(): string
    {
        return $this->name;
    }
    public function getAge(): int
    {
        return $this->age;
    }
    public function getPrice(): float
    {
        return $this->price;
    }
    public function isActive(): bool
    {
        return $this->active;
    }
    public function getScore(): ?float
    {
        return $this->score;
    }
    public function getCreatedAt(): Carbon
    {
        return $this->created_at;
    }
}

#[NoHeader(columns: [0 => 'age'])]
class InvalidAgeTestDTO
{
    #[Column]
    public int $age;
}

#[NoHeader(columns: [0 => 'price'])]
class PriceTestDTO
{
    #[Column]
    public float $price;
}

#[NoHeader(columns: [0 => 'date'])]
class DateTestDTO
{
    #[Column(format: 'd/m/Y')]
    public Carbon $date;
}

#[NoHeader(columns: [0 => 'active'])]
class BooleanTestDTO
{
    #[Column]
    public bool $active;
}

#[NoHeader(columns: [0 => 'name', 1 => 'optional'])]
class NullableTestDTO
{
    #[Column]
    public string $name;

    #[Column]
    public ?int $optional;
}

#[NoHeader(columns: [0 => 'email', 1 => 'age'])]
class ValidatedTestDTO
{
    #[Column(rule: 'required|email')]
    public string $email;

    #[Column(rule: 'required|integer|min:18')]
    public int $age;
}

/**
 * 🔮 Integration Test: Full Parser with all Casters
 */
class ParserWithCastersTest extends TestCase
{
    private ValidatorFactory $validatorFactory;

    protected function setUp(): void
    {
        $this->validatorFactory = new ValidatorFactory();
    }

    /**
     * Helper: converts parser result to array with numeric keys
     */
    private function parseToArray(Parser $parser, array $rows): array
    {
        // Updated for ParseResult
        $result = $parser->parse($rows);

        return array_values(iterator_to_array($result));
    }

    /**
     * Tests that all built-in casters work together in the Parser
     */
    public function testParserWithAllBuiltInCasters(): void
    {
        $parser = new Parser(ProductImportTestDTO::class, $this->validatorFactory);

        $rows = [
            ['Name', 'Age', 'Price', 'Active', 'Score', 'Created At'],
            ['Laptop', '3', '999.99', 'true', '4.5', '2023-01-15'],
            ['Mouse', '1', '49.50', 'yes', '', '2023-02-20'],
            ['Keyboard', '2', '89.00', '1', '4.0', '2023-03-10'],
            ['Monitor', '5', '299.00', 'false', '4.8', '2023-04-05'],
        ];

        // Parse with ParseResult
        $result = $parser->parse($rows);
        $results = [];

        foreach ($result as $entity) {
            $results[] = $entity;
        }

        $this->assertCount(4, $results, 'Should parse 4 data rows');

        // Also check statistics
        $stats = $result->getStats();
        $this->assertEquals(4, $stats->getProcessedRows());
        $this->assertEquals(4, $stats->getValidRows());
        $this->assertEquals(0, $stats->getErrorRows());

        // ... rest of the assertions remain the same ...
        $laptop = $results[0];
        $this->assertInstanceOf(ProductImportTestDTO::class, $laptop);
        $this->assertSame('Laptop', $laptop->getName());
        $this->assertSame(3, $laptop->getAge());
        $this->assertSame(999.99, $laptop->getPrice());
        $this->assertTrue($laptop->isActive());
        $this->assertSame(4.5, $laptop->getScore());
        $this->assertInstanceOf(Carbon::class, $laptop->getCreatedAt());
        $this->assertSame('2023-01-15', $laptop->getCreatedAt()->format('Y-m-d'));

        // Test nullable field
        $mouse = $results[1];
        $this->assertNull($mouse->getScore(), 'Empty string should cast to null for nullable float');

        // Test boolean from string '1'
        $keyboard = $results[2];
        $this->assertTrue($keyboard->isActive(), "'1' should cast to true");

        // Test boolean from string 'false'
        $monitor = $results[3];
        $this->assertFalse($monitor->isActive(), "'false' should cast to false");
    }

    /**
     * Tests error handling when casting fails
     */
    public function testParserThrowsCastExceptionOnInvalidData(): void
    {
        $parser = new Parser(InvalidAgeTestDTO::class, $this->validatorFactory);
        $rows = [['not a number']];

        $result = $parser->parse($rows);
        $results = iterator_to_array($result);

        $this->assertCount(0, $results, 'Should skip row with invalid number');

        // Check statistics - should have 1 error
        $stats = $result->getStats();
        $this->assertEquals(1, $stats->getProcessedRows());
        $this->assertEquals(0, $stats->getValidRows());
        $this->assertEquals(1, $stats->getErrorRows());
    }

    // ... остальные методы теста обновляются аналогично ...

    /**
     * Tests validation rules work together with casting
     */
    public function testValidationAndCastingIntegration(): void
    {
        $parser = new Parser(ValidatedTestDTO::class, $this->validatorFactory);

        // Valid data
        $validRows = [['test@example.com', '25']];
        $result = $parser->parse($validRows);
        $results = iterator_to_array($result);

        $this->assertCount(1, $results);
        $this->assertSame('test@example.com', $results[0]->email);
        $this->assertSame(25, $results[0]->age);

        // Check stats for valid data
        $stats = $result->getStats();
        $this->assertEquals(1, $stats->getProcessedRows());
        $this->assertEquals(1, $stats->getValidRows());

        // Invalid data - should throw ValidationException
        $invalidRows = [['not-an-email', '16']];
        $result = $parser->parse($invalidRows);
        $results = iterator_to_array($result);

        $this->assertCount(0, $results, 'Should skip row with invalid email');

        // Check stats for invalid data
        $stats = $result->getStats();
        $this->assertEquals(1, $stats->getProcessedRows());
        $this->assertEquals(0, $stats->getValidRows());
        $this->assertEquals(1, $stats->getErrorRows());
    }

    /**
     * New test: Verify error handler callback works
     */
    public function testErrorHandlerCallback(): void
    {
        $parser = new Parser(ValidatedTestDTO::class, $this->validatorFactory);

        $rows = [
            ['valid@example.com', '25'],
            ['invalid-email', '30'],
            ['another@valid.com', '35'],
        ];

        $errorsCollected = [];

        $result = $parser->parse($rows, function ($error) use (&$errorsCollected) {
            $errorsCollected[] = $error;
        });

        $entities = iterator_to_array($result);

        // Should have 2 valid entities
        $this->assertCount(2, $entities);

        // Should have collected 1 error
        $this->assertCount(1, $errorsCollected);

        // Check statistics
        $stats = $result->getStats();
        $this->assertEquals(3, $stats->getProcessedRows());
        $this->assertEquals(2, $stats->getValidRows());
        $this->assertEquals(1, $stats->getErrorRows());
    }

    /**
     * New test: Verify toArray() method on ParseResult
     */
    public function testParseResultToArrayMethod(): void
    {
        $parser = new Parser(ProductImportTestDTO::class, $this->validatorFactory);

        $rows = [
            ['Header'],
            ['Laptop', '3', '999.99', 'true', '4.5', '2023-01-15'],
            ['Mouse', '1', '49.50', 'yes', '', '2023-02-20'],
        ];

        $result = $parser->parse($rows);
        $entities = $result->toArray();

        $this->assertCount(2, $entities);
        $this->assertInstanceOf(ProductImportTestDTO::class, $entities[0]);
        $this->assertInstanceOf(ProductImportTestDTO::class, $entities[1]);
    }
}
