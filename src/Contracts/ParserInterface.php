<?php
declare(strict_types=1);

namespace Shmandalf\Excelentor\Contracts;

use Shmandalf\Excelentor\Exceptions\ValidationException;

interface ParserInterface
{
    /**
     * Проверяет все возможные строки сразу, возвращая массив с ошибками если есть
     *
     * @param iterable $rows
     * @return ValidationException[]
     */
    public function validateAll(iterable $rows): array;

    /**
     * Валидирует и парсит таблицу
     *
     * @param iterable $rows
     * @return \Generator|object[] - массив с POPO
     * @throws ValidationException
     */
    public function parse(iterable $rows): \Generator;
}


