<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Shmandalf\Excelentor\Attributes\{Column, NoHeader};
use Shmandalf\Excelentor\Parser;
use Shmandalf\Excelentor\ValidatorFactory;

// Simple test DTO
#[NoHeader(columns: ['name', 'value'])]
class SimpleTestDTO
{
    #[Column]
    public string $name;

    #[Column(rule: 'required|integer')]
    public int $value;
}

class ParseResultTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $validatorFactory = new ValidatorFactory('en');
        $this->parser = new Parser(SimpleTestDTO::class, $validatorFactory);
    }

    public function testParseResultBasicFunctionality(): void
    {
        $rows = [
            ['Item1', '100'],
            ['Item2', '200'],
        ];

        $result = $this->parser->parse($rows);

        // Test that it implements IteratorAggregate
        $this->assertInstanceOf(\IteratorAggregate::class, $result);

        // Test iteration
        $items = [];

        foreach ($result as $item) {
            $this->assertInstanceOf(SimpleTestDTO::class, $item);
            $items[] = $item;
        }

        $this->assertCount(2, $items);
        $this->assertEquals('Item1', $items[0]->name);
        $this->assertEquals('Item2', $items[1]->name);
    }

    public function testStatisticsAreCollected(): void
    {
        // Mix of valid and invalid data
        $rows = [
            ['Item1', '100'],    // Valid
            ['Item2', 'invalid'], // Invalid - not an integer
            ['Item3', '300'],     // Valid
        ];

        $result = $this->parser->parse($rows);

        // Force processing
        $entities = iterator_to_array($result);

        $stats = $result->getStats();

        $this->assertEquals(3, $stats->getProcessedRows());
        $this->assertEquals(2, $stats->getValidRows());
        $this->assertEquals(1, $stats->getErrorRows());
        $this->assertEqualsWithDelta(66.67, $stats->getSuccessRate(), 0.01, '');
        $this->assertNotNull($stats->getProcessingTime());
        $this->assertGreaterThan(0, $stats->getProcessingTime());
    }

    public function testParseResultCanOnlyBeIteratedOnce(): void
    {
        $rows = [
            ['Item1', '100'],
            ['Item2', '200'],
        ];

        $result = $this->parser->parse($rows);

        // First iteration - should work
        $count1 = 0;

        foreach ($result as $entity) {
            $count1++;
        }
        $this->assertEquals(2, $count1);

        // Second iteration - should throw exception
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ParseResult can only be iterated once');

        foreach ($result as $entity) {
            // This should throw
        }
    }

    public function testErrorHandlerCallback(): void
    {
        $rows = [
            ['Item1', 'invalid'],
        ];

        $errorTriggered = false;
        $errorMessage = '';

        $result = $this->parser->parse($rows, function ($error) use (&$errorTriggered, &$errorMessage) {
            $errorTriggered = true;
            $errorMessage = $error->getMessage();
        });

        iterator_to_array($result);

        $this->assertTrue($errorTriggered);
        $this->assertStringContainsString('integer', $errorMessage);
    }

    public function testErrorHandlerCanStopProcessing(): void
    {
        $rows = [
            ['Item1', 'invalid'], // This will trigger error
            ['Item2', '200'],     // This should not be processed
        ];

        $this->expectException(\RuntimeException::class);

        $result = $this->parser->parse($rows, function () {
            throw new \RuntimeException('Stop on first error');
        });

        iterator_to_array($result);
    }

    public function testToArrayMethod(): void
    {
        $rows = [
            ['Item1', '100'],
            ['Item2', '200'],
            ['Item3', 'invalid'], // Error
            ['Item4', '400'],
        ];

        $result = $this->parser->parse($rows);
        $entities = $result->toArray();

        // Should contain only valid entities
        $this->assertCount(3, $entities);

        // Check stats after toArray()
        $stats = $result->getStats();
        $this->assertEquals(4, $stats->getProcessedRows());
        $this->assertEquals(3, $stats->getValidRows());
        $this->assertEquals(1, $stats->getErrorRows());

        // Не пытаемся итерировать после toArray() - это выбросит исключение
        // Если хочешь проверить что нельзя - добавь expectException
    }

    public function testStatsAreAvailableDuringIteration(): void
    {
        $rows = [
            ['Item1', '100'],
            ['Item2', 'invalid'],
            ['Item3', '300'],
        ];

        $result = $this->parser->parse($rows);

        $processedCounts = [];

        $stats = $result->getStats();

        foreach ($result as $index => $entity) {
            $processedCounts[] = $stats->getProcessedRows();
        }

        // Stats should update during iteration
        $this->assertEquals([1, 3], $processedCounts);
    }

    public function testIsFinishedMethod(): void
    {
        $rows = [
            ['Item1', '100'],
        ];

        $result = $this->parser->parse($rows);

        // Not finished before iteration
        $this->assertFalse($result->isFinished());

        // Iterate
        foreach ($result as $entity) {
            // During iteration
        }

        // Finished after iteration
        $this->assertTrue($result->isFinished());

        // Теперь нельзя итерировать снова
        $this->expectException(\RuntimeException::class);

        foreach ($result as $entity) {
            // Should throw
        }
    }
}
