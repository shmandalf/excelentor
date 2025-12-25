<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Contracts;

use Shmandalf\Excelentor\Exceptions\ValidationException;

interface ParserInterface
{
    /**
     * Validate all rows
     *
     * Returns array with validation exceptions
     *
     * @return ValidationException[]
     */
    public function validateAll(iterable $rows): array;

    /**
     * Parse data
     *
     * @throws ValidationException
     * @return \Generator|object[] - resulting DTOs
     */
    public function parse(iterable $rows): \Generator;
}
