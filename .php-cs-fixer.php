<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('coverage')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'try', 'if', 'switch', 'foreach'],
        ],
        'braces' => [
            'allow_single_line_closure' => true,
            'position_after_functions_and_oop_constructs' => 'same',
        ],
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => true,
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
        'no_superfluous_phpdoc_tags' => true,
        'phpdoc_order' => true,
        'yoda_style' => false,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(true);
