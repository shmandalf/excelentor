<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Shmandalf\Excelentor\Attributes\{CasterConfig, Column, NoHeader};
use Shmandalf\Excelentor\Parser;
use Shmandalf\Excelentor\Tests\Fixtures\LowercaseString;
use Shmandalf\Excelentor\Tests\Fixtures\LowerCaster;
use Shmandalf\Excelentor\Tests\Fixtures\UppercaseString;
use Shmandalf\Excelentor\Tests\Fixtures\UpperCaster;
use Shmandalf\Excelentor\ValidatorFactory;

#[CasterConfig([
    'upper' => UpperCaster::class,
    'lower' => LowerCaster::class,
])]
#[NoHeader(columns: [0 => 'name'])]
class ConfigTestAliasDTO
{
    #[Column(caster: 'upper')]
    public string $name;
}

////////////////
#[CasterConfig([
    'string' => UpperCaster::class, // Map 'string' type to UpperCaster
])]
#[NoHeader(columns: [0 => 'value'])]
class TestTypeMappingDTO
{
    #[Column] // No caster specified - should use type mapping
    public string $value;
}

////////////////
#[NoHeader(columns: [0 => 'value'])]
class ConfigTestOverrideDTO
{
    #[Column]
    public string $value;
}

// Create test DTO with multiple aliases
#[CasterConfig([
    'upper' => UpperCaster::class,
    'lower' => LowerCaster::class,
])]
#[NoHeader(columns: ['name1', 'name2'])]
class ConfigTestMultiAliasDTO
{
    #[Column(caster: 'upper')]
    public UppercaseString $name1;

    #[Column(caster: 'lower')]
    public LowercaseString $name2;
}

class OverrideCaster implements \Shmandalf\Excelentor\Contracts\CasterInterface
{
    public function cast($value, ?string $format = null): string
    {
        return 'override:' . (string)$value;
    }
}

/**
 * Test class for CasterConfig functionality
 */
class CasterConfigTest extends TestCase
{
    private ValidatorFactory $validatorFactory;

    protected function setUp(): void
    {
        $this->validatorFactory = new ValidatorFactory();
    }

    public function testCasterAliasFromConfig(): void
    {
        $parser = new Parser(ConfigTestAliasDTO::class, $this->validatorFactory);

        $rows = [['hello world']];
        $result = $parser->parse($rows);
        $entities = $result->toArray();

        $this->assertCount(1, $entities);
        $this->assertSame('HELLO WORLD', $entities[0]->name); // UpperCaster should uppercase
    }

    public function testWithCastOverridesCasterConfig(): void
    {
        $parser = new Parser(ConfigTestOverrideDTO::class, $this->validatorFactory);

        // Override the caster registered by CasterConfig
        $overrideParser = $parser->withCast(new OverrideCaster(), 'string');

        $rows = [['test']];
        $result = $overrideParser->parse($rows);
        $entities = $result->toArray();

        $this->assertCount(1, $entities);
        $this->assertSame('override:test', $entities[0]->value); // Should use override, not config
    }

    public function testCasterConfigWithMultipleAliases(): void
    {
        $parser = new Parser(ConfigTestMultiAliasDTO::class, $this->validatorFactory);

        $rows = [['Hello', 'WORLD']];
        $result = $parser->parse($rows);
        $entities = $result->toArray();

        $this->assertCount(1, $entities);
        $this->assertSame('HELLO', $entities[0]->name1->getValue()); // UpperCaster
        $this->assertSame('world', $entities[0]->name2->getValue()); // LowerCaster
    }

    public function testCasterConfigWithoutAliasUsesTypeMapping(): void
    {
        // Test that when caster is not specified in Column,
        // it uses type-based mapping from CasterConfig
        $parser = new Parser(TestTypeMappingDTO::class, $this->validatorFactory);

        $rows = [['test']];
        $result = $parser->parse($rows);
        $entities = $result->toArray();

        $this->assertCount(1, $entities);
        $this->assertSame('TEST', $entities[0]->value); // Should be uppercase
    }
}
