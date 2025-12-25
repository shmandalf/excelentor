<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Tests\Fixtures;

use Stringable;

class UppercaseString implements Stringable
{
    public function __construct(private string $value)
    {
    }
    public function getValue(): string
    {
        return strtoupper($this->value);
    }

    public function __toString(): string
    {
        return $this->getValue();
    }
}

class LowercaseString implements Stringable
{
    public function __construct(private string $value)
    {
    }
    public function getValue(): string
    {
        return strtolower($this->value);
    }

    public function __toString(): string
    {
        return $this->getValue();
    }
}

class UpperCaster implements \Shmandalf\Excelentor\Contracts\CasterInterface
{
    public function cast($value, ?string $format = null): UppercaseString
    {
        return new UppercaseString(strtoupper($value));
    }
}

class LowerCaster implements \Shmandalf\Excelentor\Contracts\CasterInterface
{
    public function cast($value, ?string $format = null): LowercaseString
    {
        return new LowercaseString(strtolower($value));
    }
}
