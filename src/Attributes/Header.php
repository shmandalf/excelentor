<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Attributes;

use Attribute;
use Shmandalf\Excelentor\Exceptions\ParserException;

#[Attribute(Attribute::TARGET_CLASS)]
class Header
{
    /**
     * Number of rows occupied by the header
     *
     * @var integer
     */
    private int $rows;

    /**
     * Column mapping.
     *
     * An array where:
     * - key is the column index
     * - value is the property name
     *
     */
    private array $columns;

    /**
     * Global validation messages, i.e., not bound to specific columns
     *
     */
    private array $messages;

    /**
     * Whether to stop execution on the first validation failure
     *
     */
    private bool $stopOnFirstFailure;

    /**
     * Constructor.
     *
     * The number of rows occupied by the header must be provided.
     * If there is no header, use NoHeader instead.
     *
     * @see NoHeader
     *
     * @param array   $columns  - column mappings (index -> property name)
     * @param integer $rows     - number of header rows
     *
     */
    public function __construct(
        array $columns,
        int $rows = 1,
        array $messages = [],
        bool $stopOnFirstFailure = false
    ) {
        if (empty($columns)) {
            throw new ParserException('At least one column must be specified in the header');
        }

        $this->columns = $columns;
        $this->rows = $rows;
        $this->messages = $messages;
        $this->stopOnFirstFailure = $stopOnFirstFailure;
    }

    /**
     * Getter for the number of header rows
     *
     * @return integer
     */
    public function getRows(): int
    {
        return $this->rows;
    }

    /**
     * Returns the column index by its name.
     *
     * In $columns, keys can be either numeric or string in Excel column format (e.g., "A", "AB").
     *
     * @param  string $name - column (property) name
     */
    public function getColumnIndex(string $name): int
    {
        // Convert to $name => $index mapping
        $columns = array_flip($this->columns);
        $index = $columns[$name] ?? null;

        if ($index === null) {
            throw new ParserException("The column name `{$name}` is not specified in the header columns");
        }

        if (is_string($index)) {
            if (empty($index)) {
                throw new ParserException("Empty string index for column `{$name}`");
            }

            // If the index starts with a letter, convert it to an integer
            if (ctype_alnum(substr($index, 0, 1))) {
                $index = $this->excelColumnNameToNumber($index);
            }
        }

        return (int) $index;
    }

    /**
     * Returns an array with column indexes
     *
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Returns an array with "global" validation messages
     *
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function shouldStopOnFirstFailure(): bool
    {
        return $this->stopOnFirstFailure;
    }

    /**
     * Converts an Excel-style column name (e.g., "A", "AB") to a zero-based numeric index
     *
     * @return integer
     */
    private function excelColumnNameToNumber(string $name): int
    {
        $value = 0;

        foreach (str_split(strtoupper($name)) as $char) {
            $value = $value * 26 + (ord($char) - ord('A') + 1);
        }

        // Since internal indexes are zero-based, subtract one
        $value--;

        return $value;
    }
}
