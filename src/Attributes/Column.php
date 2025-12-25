<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    /**
     * Validation rule
     *
     * @var string|null
     */
    private ?string $rule;

    /**
     * Custom messages about validation errors
     *
     * @var array
     */
    private array $messages;

    /**
     * Column format
     *
     * @var string|null
     */
    private ?string $format;

    /**
     * A row is treated not "empty" if all "mandatory" columns are present in the row
     * Otherwise the row will be skipped
     *
     * @var boolean
     */
    private bool $mandatory;

    /**
     * @param string|null  $rule      - validation rule
     * @param string       $format    - format (e.g. for parsing dates)
     * @param array        $messages  - optional array with custom messages about validation errors
     * @param boolean      $mandatory - if `true`, the value must not be empty for the row to be processed
     */
    public function __construct(?string $rule = null, ?string $format = null, array $messages = [], bool $mandatory = false)
    {
        $this->rule = $rule;
        $this->format = $format;
        $this->messages = $messages;
        $this->mandatory = $mandatory;
    }

    /**
     * @return string|null
     */
    public function getRule(): ?string
    {
        return $this->rule;
    }

    /**
     * @return array
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
     * @return string|null
     */
    public function getFormat(): ?string
    {
        return $this->format;
    }
}
