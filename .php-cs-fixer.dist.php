<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = new Finder()
    ->in([
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/config',
        __DIR__.'/bin',
        __DIR__.'/tools',
    ])
    ->notName('reference.php')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return new Config()
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__.'/.phpunit.result.cache')
    ->setRules([
        '@Symfony' => true,
        'strict_param' => true,
        'psr_autoloading' => true,
        'declare_strict_types' => true,
        'method_chaining_indentation' => true,
        'curly_braces_position' => [
            'functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
        ],
        'single_line_empty_body' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'fully_qualified_strict_types' => [
            'import_symbols' => true,
        ],
        'no_leading_import_slash' => true,
        'no_unused_imports' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
    ])
    ->setFinder($finder);
