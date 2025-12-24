<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Contracts;

interface CasterInterface
{
    /**
     * 🔮 Transmutes raw spreadsheet essence into purified type mana
     *
     * @param mixed $value Raw value from spreadsheet (string, int, float, bool, null, etc.)
     * @param string|null $format Optional transmutation format (e.g., date format)
     * @return mixed Purified value of the desired type
     */
    public function cast(mixed $value, ?string $format = null): mixed;
}
