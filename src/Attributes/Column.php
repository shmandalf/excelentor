<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Attributes;

use Attribute;

/**
 * Defines column mapping for a DTO property
 *
 * This attribute can be used to:
 * 1. Specify validation rules
 * 2. Mark column as mandatory
 * 3. Provide format hints (e.g., date format)
 * 4. Explicitly specify caster alias from CasterConfig
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    /**
     * @param string|null  $rule      - Laravel-style validation rule string
     * @param string|null  $format    - Format hint for caster (e.g., date format)
     * @param array        $messages  - Optional array with custom messages about validation errors
     * @param boolean      $mandatory - Whether column must be present in the row to be processed
     * @param string|null  $caster    - Caster alias from CasterConfig to use for this property
     *                                  If null, caster will be resolved by property type
     */
    public function __construct(
        public ?string $rule = null,
        public ?string $format = null,
        public array $messages = [],
        public bool $mandatory = false,
        public ?string $caster = null
    ) {
    }

    /**
     */
    public function getRule(): ?string
    {
        return $this->rule;
    }

    /**
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @return boolean
     */
    public function isMandatory(): bool
    {
        return $this->mandatory;
    }

    /**
     */
    public function getFormat(): ?string
    {
        return $this->format;
    }
}
