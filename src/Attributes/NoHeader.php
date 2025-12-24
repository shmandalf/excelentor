<?php
declare(strict_types=1);

namespace Shmandalf\Excelentor\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class NoHeader extends Header
{
    /**
     * Конструктор
     *
     * @param array   $columns  - маппинги столбцов (index -> имя пропса)
     * @param array   $messages - "глобальные" сообщения валидации
     * @param bool    $stopOnFirstFailure - останоить ли выполнение в случае первой ошибки
     */
    public function __construct(array $columns, array $messages = [], bool $stopOnFirstFailure = false)
    {
        // Отсутствует header. Используем 0 строк, но используем маппинги
        parent::__construct($columns, 0, $messages, $stopOnFirstFailure);
    }
}
