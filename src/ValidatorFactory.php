<?php

namespace Shmandalf\Excelentor;

use Illuminate\Validation\Factory;
use Illuminate\Translation\Translator;
use Illuminate\Translation\ArrayLoader;

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
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @return \Illuminate\Validation\Validator
     */
    public function make(array $data, array $rules, array $messages = [])
    {
        return $this->factory->make($data, $rules, $messages);
    }
}
