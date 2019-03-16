<?php

return PhpCsFixer\Config::create()
    ->setRules([
        '@Symfony' => trque,
        '@Symfony:risky' => false,
        'concat_space' => ['spacing' => 'one'],
        'increment_style' => ['style' => 'post'],
        'array_syntax' => ['syntax' => 'short'],
        'simplified_null_return' => false,
        'phpdoc_align' => false,
        'phpdoc_to_comment' => false,
        'cast_spaces' => false,
        'blank_line_after_opening_tag' => false,
        'single_blank_line_before_namespace' => true,
        'space_after_semicolon' => false,
        'yoda_style' => false,
        'no_break_comment' => false,
        'declare_strict_types' => true,
        'native_function_invocation' => false,
        'phpdoc_types_order' => false,
        'psr4' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in([__DIR__ . '/src', __DIR__ . '/tests'])
            ->files()->name('*.php')
    )
;
