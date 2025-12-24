<?php
declare(strict_types=1);

namespace Shmandalf\Excelentor\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    /**
     * Правило валидации
     *
     * @var string|null
     */
    private ?string $rule;

    /**
     * Кастомные сообщения об ошибках валидации
     *
     * @var array
     */
    private array $messages;

    /**
     * Формат столбца.
     *
     * Может использоваться для импорта дат и прочего.
     *
     * @var string|null
     */
    private ?string $format;

    /**
     * Строка считается "не пустой", если все столбцы с этим флагом присутствуют в строке
     *
     * @var boolean
     */
    private bool $mandatory;

    /**
     * Конструктор
     *
     * @param string|null  $rule      - правило валидации
     * @param string       $format    - формат (e.g. для импорта дат)
     * @param array        $messages  - опциональный массив с кастомными сообщениями об ошибке валидации
     * @param boolean      $mandatory - `true`, если значение столбца должно присутствовать в строке, чтобы она не считалась "пустой"
     */
    public function __construct(?string $rule = null, ?string $format = null, array $messages = [], bool $mandatory = false)
    {
        $this->rule = $rule;
        $this->format = $format;
        $this->messages = $messages;
        $this->mandatory = $mandatory;
    }

    /**
     * Геттер правила валидации
     *
     * @return string|null
     */
    public function getRule(): ?string
    {
        return $this->rule;
    }

    /**
     * Геттер кастомных сообщений об ошибках валидации
     *
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Геттер для "mandatory"
     *
     * @return boolean
     */
    public function isMandatory(): bool
    {
        return $this->mandatory;
    }

    /**
     * Геттер формата столбца.
     *
     * @return string|null
     */
    public function getFormat(): ?string
    {
        return $this->format;
    }
}