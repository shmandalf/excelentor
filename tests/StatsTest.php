<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Tests;

use PHPUnit\Framework\TestCase;
use Shmandalf\Excelentor\Stats;

class StatsTest extends TestCase
{
    public function test_stats_initial_state(): void
    {
        $stats = new Stats();

        $this->assertEquals(0, $stats->getProcessedRows());
        $this->assertEquals(0, $stats->getValidRows());
        $this->assertEquals(0, $stats->getErrorRows());
        $this->assertEquals(0.0, $stats->getSuccessRate());
        $this->assertNull($stats->getProcessingTime());
    }

    public function test_stats_increment_methods(): void
    {
        $stats = new Stats();

        $stats->incrementProcessed();
        $stats->incrementProcessed();
        $this->assertEquals(2, $stats->getProcessedRows());

        $stats->incrementValid();
        $this->assertEquals(1, $stats->getValidRows());

        $stats->incrementErrors();
        $stats->incrementErrors();
        $this->assertEquals(2, $stats->getErrorRows());
    }

    public function test_success_rate_calculation(): void
    {
        $stats = new Stats();

        // No rows processed
        $this->assertEquals(0.0, $stats->getSuccessRate());

        // 2 processed, 1 valid = 50%
        $stats->incrementProcessed();
        $stats->incrementProcessed();
        $stats->incrementValid();
        $this->assertEquals(50.0, $stats->getSuccessRate());

        // 4 processed, 3 valid = 75%
        $stats->incrementProcessed();
        $stats->incrementProcessed();
        $stats->incrementValid();
        $stats->incrementValid();
        $this->assertEquals(75.0, $stats->getSuccessRate());
    }

    public function test_processing_time(): void
    {
        $stats = new Stats();

        // Not finished yet
        $this->assertNull($stats->getProcessingTime());

        // Finish and check time
        $stats->finish();
        $processingTime = $stats->getProcessingTime();

        $this->assertNotNull($processingTime);
        $this->assertGreaterThan(0, $processingTime);
        $this->assertLessThan(1, $processingTime); // Should be very fast
    }

    public function test_to_array_and_json_serialize(): void
    {
        $stats = new Stats();

        $stats->incrementProcessed();
        $stats->incrementProcessed();
        $stats->incrementValid();
        $stats->incrementErrors();
        $stats->finish();

        $array = $stats->toArray();

        $this->assertArrayHasKey('processed_rows', $array);
        $this->assertArrayHasKey('valid_rows', $array);
        $this->assertArrayHasKey('error_rows', $array);
        $this->assertArrayHasKey('success_rate', $array);
        $this->assertArrayHasKey('processing_time', $array);

        $this->assertEquals(2, $array['processed_rows']);
        $this->assertEquals(1, $array['valid_rows']);
        $this->assertEquals(1, $array['error_rows']);
        $this->assertEquals(50.0, $array['success_rate']);
        $this->assertNotNull($array['processing_time']);

        // Test JSON serialize returns same array
        $this->assertEquals($array, $stats->jsonSerialize());
    }
}
