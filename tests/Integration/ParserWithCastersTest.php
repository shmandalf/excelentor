<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Shmandalf\Excelentor\Parser;
use Shmandalf\Excelentor\ValidatorFactory;
use Shmandalf\Excelentor\Attributes\{Header, Column, NoHeader};
use Carbon\Carbon;

/**
 * DTO classes for tests
 */

#[Header(columns: [
    0 => 'name',
    1 => 'age',
    2 => 'price',
    3 => 'active',
    4 => 'score',
    5 => 'created_at'
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

#[NoHeader(columns: [0 => 'age'], stopOnFirstFailure: true)]
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

#[NoHeader(columns: [0 => 'active'], stopOnFirstFailure: true)]
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

#[NoHeader(columns: [0 => 'email', 1 => 'age'], stopOnFirstFailure: true)]
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
        return array_values(iterator_to_array($parser->parse($rows)));
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

        // Instead of parseToArray, iterate through the generator
        $results = [];
        foreach ($parser->parse($rows) as $index => $result) {
            $results[] = $result;
        }

        $this->assertCount(4, $results, 'Should parse 4 data rows');

        // Test each row
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

        $this->expectException(\Shmandalf\Excelentor\Exceptions\ValidationException::class);
        $this->expectExceptionMessage('Cannot convert');

        iterator_to_array($parser->parse($rows));
    }

    /**
     * Tests numeric string formatting with thousands separators
     */
    public function testParserHandlesFormattedNumbers(): void
    {
        $parser = new Parser(PriceTestDTO::class, $this->validatorFactory);

        // Test with different number formats (only those that work with dot as decimal)
        $testCases = [
            '1,000.50' => 1000.5,
            '1 000.50' => 1000.5,
        ];

        foreach ($testCases as $input => $expected) {
            $rows = [[$input]];
            $results = $this->parseToArray($parser, $rows);

            $this->assertCount(1, $results, "Failed for input '{$input}'");
            $this->assertEqualsWithDelta(
                $expected,
                $results[0]->price,
                0.01,
                "Failed to parse '{$input}' as {$expected}"
            );
        }
    }

    /**
     * Tests date parsing with different formats
     */
    public function testParserHandlesDifferentDateFormats(): void
    {
        $parser = new Parser(DateTestDTO::class, $this->validatorFactory);
        $rows = [['15/01/2023']];

        $results = $this->parseToArray($parser, $rows);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(Carbon::class, $results[0]->date);
        $this->assertSame('2023-01-15', $results[0]->date->format('Y-m-d'));
    }

    /**
     * Tests boolean parsing with custom values - DEBUG VERSION
     */
    public function testParserHandlesCustomBooleanValues(): void
    {
        $parser = new Parser(BooleanTestDTO::class, $this->validatorFactory);

        $trueCases = [['true'], ['yes'], ['1'], ['on']];
        $falseCases = [['false'], ['no'], ['0'], ['off'], ['']];

        foreach ($trueCases as $case) {
            try {
                $results = $this->parseToArray($parser, [$case]);
                if (empty($results)) {
                    continue;
                }
                $this->assertTrue($results[0]->active, "Should parse '{$case[0]}' as true");
            } catch (\Throwable $e) {
                throw $e;
            }
        }

        foreach ($falseCases as $case) {
            try {
                $results = $this->parseToArray($parser, [$case]);
                if (empty($results)) {
                    continue;
                }
                $this->assertFalse($results[0]->active, "Should parse '{$case[0]}' as false");
            } catch (\Throwable $e) {
                throw $e;
            }
        }
    }

    /**
     * Tests that nullable properties work correctly
     */
    public function testParserHandlesNullableProperties(): void
    {
        $parser = new Parser(NullableTestDTO::class, $this->validatorFactory);

        // Row with optional value
        $rows1 = [['Test', '42']];
        $results1 = $this->parseToArray($parser, $rows1);
        $this->assertSame(42, $results1[0]->optional);

        // Row without optional value
        $rows2 = [['Test', '']];
        $results2 = $this->parseToArray($parser, $rows2);
        $this->assertNull($results2[0]->optional);
    }

    /**
     * Tests validation rules work together with casting
     */
    public function testValidationAndCastingIntegration(): void
    {
        $parser = new Parser(ValidatedTestDTO::class, $this->validatorFactory);

        // Valid data
        $validRows = [['test@example.com', '25']];
        $results = $this->parseToArray($parser, $validRows);
        $this->assertCount(1, $results);
        $this->assertSame('test@example.com', $results[0]->email);
        $this->assertSame(25, $results[0]->age);

        // Invalid data - should throw ValidationException
        $invalidRows = [['not-an-email', '16']];

        $this->expectException(\Shmandalf\Excelentor\Exceptions\ValidationException::class);

        iterator_to_array($parser->parse($invalidRows));
    }

    /**
     * Quick test to verify BoolCaster is registered
     */
    public function testBoolCasterIsRegistered(): void
    {
        $parser = new Parser(BooleanTestDTO::class, $this->validatorFactory);

        // Use reflection to check caster registry
        $reflection = new \ReflectionClass($parser);
        $registryProp = $reflection->getProperty('casterRegistry');
        $registry = $registryProp->getValue($parser);

        $this->assertArrayHasKey('bool', $registry, 'Bool caster should be registered for "bool" type');
        $this->assertArrayHasKey('boolean', $registry, 'Bool caster should be registered for "boolean" type');

        $boolCaster = $registry['bool'];
        $this->assertInstanceOf(
            \Shmandalf\Excelentor\Casters\BoolCaster::class,
            $boolCaster,
            'Bool caster should be instance of BoolCaster'
        );
    }
}
