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
     * @param iterable $rows
     * @return ValidationException[]
     */
    public function validateAll(iterable $rows): array;

    /**
     * Parse data
     *
     * @param iterable $rows
     * @return \Generator|object[] - resulting DTOs
     * @throws ValidationException
     */
    public function parse(iterable $rows): \Generator;
}
