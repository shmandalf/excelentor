<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Casters;

use Carbon\Carbon;
use DateTimeInterface;
use InvalidArgumentException;
use Shmandalf\Excelentor\Contracts\CasterInterface;

/**
 * 🔮 Date & Time Transmutation Spell
 *
 * Converts spreadsheet temporal essence into Carbon time mana.
 * Supports multiple date formats, timezone handling, and fallbacks.
 */
class DateCaster implements CasterInterface
{
    private ?string $timezone;
    private array $fallbackFormats;

    /**
     * @param string|null $timezone Target timezone (null = use input timezone)
     * @param array $fallbackFormats Additional date formats to try if primary fails
     */
    public function __construct(
        ?string $timezone = null,
        array $fallbackFormats = []
    ) {
        $this->timezone = $timezone;
        $this->fallbackFormats = $fallbackFormats;
    }

    public function cast(mixed $value, ?string $format = null): Carbon
    {
        if ($value === null) {
            throw new InvalidArgumentException('Cannot convert null to date');
        }

        // Already a Carbon instance
        if ($value instanceof Carbon) {
            return $this->applyTimezone($value);
        }

        // Other DateTime instances
        if ($value instanceof DateTimeInterface) {
            return $this->applyTimezone(Carbon::instance($value));
        }

        // Numeric timestamp (Unix timestamp or Excel serial date)
        if (is_int($value) || is_float($value)) {
            return $this->castFromTimestamp($value);
        }

        // Boolean - throw or convert?
        if (is_bool($value)) {
            throw new InvalidArgumentException('Cannot convert boolean to date');
        }

        // String - main conversion path
        if (is_string($value)) {
            return $this->castFromString($value, $format);
        }

        // Try via StringCaster as last resort
        try {
            $stringValue = (new StringCaster())->cast($value);

            return $this->castFromString($stringValue, $format);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(
                sprintf('Cannot convert %s to date: %s', gettype($value), $e->getMessage())
            );
        }
    }

    private function castFromTimestamp(int|float $timestamp): Carbon
    {
        // Check if it's an Excel serial date (days since 1900-01-00)
        if ($timestamp > 60 && $timestamp < 2958465) {
            // Likely Excel date - 25569 = days from 1900 to 1970
            $unixTimestamp = ($timestamp - 25569) * 86400;

            return Carbon::createFromTimestamp((int) $unixTimestamp);
        }

        // Assume Unix timestamp
        return Carbon::createFromTimestamp((int) $timestamp);
    }

    private function castFromString(string $value, ?string $format = null): Carbon
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Empty string cannot be converted to date');
        }

        // Try with specified format first
        if ($format !== null) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date !== false) {
                    return $this->applyTimezone($date);
                }
            } catch (\Throwable $e) {
                // Continue to try other methods
            }
        }

        // Try fallback formats
        foreach ($this->fallbackFormats as $fallbackFormat) {
            try {
                $date = Carbon::createFromFormat($fallbackFormat, $value);

                if ($date !== false) {
                    return $this->applyTimezone($date);
                }
            } catch (\Throwable $e) {
                // Continue
            }
        }

        // Try Carbon's intelligent parsing
        try {
            return $this->applyTimezone(Carbon::parse($value));
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                sprintf('Cannot parse date string "%s". %s', $value, $e->getMessage())
            );
        }
    }

    private function applyTimezone(Carbon $date): Carbon
    {
        if ($this->timezone !== null) {
            return $date->setTimezone($this->timezone);
        }

        return $date;
    }
}
