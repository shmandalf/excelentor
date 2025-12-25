<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor\Tests\Fixtures;

use Carbon\Carbon;
use Shmandalf\Excelentor\Attributes\Column;
use Shmandalf\Excelentor\Attributes\NoHeader;

#[NoHeader(
    columns: [
        'email',
        'int',
        'date',
        'string',
        'float',
    ],
    messages: [
        'required' => 'The required `:attribute` is missing',
    ]
)]
class ValidFixture
{
    #[Column(rule: 'email')]
    public string $email;

    #[Column(mandatory: true, rule: 'integer')]
    public int $int;

    #[Column(rule: 'nullable|date')]
    public ?Carbon $date;

    #[Column]
    public string $string = '';

    #[Column]
    public ?float $float;
}
