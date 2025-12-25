<?php

declare(strict_types=1);

namespace Shmandalf\Excelentor;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

/**
 * Using Illuminate Validator at this moment only
 */
class ValidatorFactory
{
    private Factory $factory;

    public function __construct(string $locale = 'ru')
    {
        $loader = new ArrayLoader();
        $translator = new Translator($loader, $locale);

        $this->factory = new Factory($translator);
    }

    /**
     * @return \Illuminate\Validation\Validator
     */
    public function make(array $data, array $rules, array $messages = [])
    {
        return $this->factory->make($data, $rules, $messages);
    }
}
