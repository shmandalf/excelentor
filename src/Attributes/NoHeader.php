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
     * @param bool    $stopOnFirstFailure - whether to stop execution on the first error
     */
    public function __construct(array $columns, array $messages = [], bool $stopOnFirstFailure = false)
    {
        // No header present. Use 0 rows but apply mappings
        parent::__construct($columns, 0, $messages, $stopOnFirstFailure);
    }
}
