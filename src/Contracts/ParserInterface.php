<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Contracts;

use Shmandalf\Excelentor\Exceptions\ValidationException;
use Shmandalf\Excelentor\ParseResult;

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
     * Parse rows with statistics and error handling
     *
     * @param iterable $rows Input data
     * @param callable|null $errorHandler Callback for error handling
     */
    public function parse(iterable $rows, ?callable $errorHandler = null): ParseResult;
}
