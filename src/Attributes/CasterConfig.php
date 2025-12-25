<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Attributes;

use Attribute;

/**
 * Defines caster configuration for a DTO class
 *
 * This attribute maps types or aliases to caster classes with optional constructor arguments.
 * It can be placed on a DTO class to automatically register casters for its properties.
 *
 * Example:
 * #[CasterConfig([
 *     Money::class => [MoneyCaster::class, 'EUR'],
 *     'price_usd' => [MoneyCaster::class, 'USD'],
 *     Uuid::class => UuidCaster::class,
 * ])]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class CasterConfig
{
    /**
     * @param array<string, string|array> $config Caster configuration
     *
     * Configuration format:
     * - Type mapping: ClassName::class => CasterClass::class
     * - Type mapping with args: ClassName::class => [CasterClass::class, ...args]
     * - Alias mapping: 'alias_name' => CasterClass::class
     * - Alias mapping with args: 'alias_name' => [CasterClass::class, ...args]
     */
    public function __construct(
        public readonly array $config
    ) {
    }
}
