<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class NoHeader extends Header
{
    /**
     * Constructor
     *
     * @param array   $columns  - column mappings (index -> property name)
     * @param array   $messages - "global" validation messages
     */
    public function __construct(array $columns, array $messages = [])
    {
        // No header present. Use 0 rows but apply mappings
        parent::__construct($columns, 0, $messages);
    }
}
