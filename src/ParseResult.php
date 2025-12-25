<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor;

use Shmandalf\Excelentor\Exceptions\ValidationException;

class ParseResult implements \IteratorAggregate
{
    private \Generator $generator;
    private Stats $stats;
    private bool $finished = false;
    private bool $iterated = false;

    /**
     * @param callable $processor Factory that creates a generator
     * @param callable|null $errorHandler Callback for error handling
     */
    public function __construct(callable $processor, ?callable $errorHandler = null)
    {
        $this->stats = new Stats();
        $this->generator = $this->createGenerator($processor, $errorHandler);
    }

    private function createGenerator(callable $processor, ?callable $errorHandler): \Generator
    {
        $generator = $processor();

        foreach ($generator as $item) {
            $this->stats->incrementProcessed();

            if ($item instanceof ValidationException) {
                $this->stats->incrementErrors();

                // Call user error handler if provided
                if ($errorHandler) {
                    $errorHandler($item, $this->stats);
                }

                continue;
            }

            $this->stats->incrementValid();
            yield $item;
        }

        $this->stats->finish();
        $this->finished = true;
    }

    public function getIterator(): \Generator
    {
        if ($this->iterated) {
            throw new \RuntimeException('ParseResult can only be iterated once');
        }

        $this->iterated = true;

        return $this->generator;
    }

    /**
     * Get all entities as array
     * Warning: loads all entities into memory
     */
    public function toArray(): array
    {
        if ($this->iterated) {
            throw new \RuntimeException('ParseResult has already been iterated');
        }

        return iterator_to_array($this->generator, false);
    }

    public function getStats(): Stats
    {
        return $this->stats;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }
}
