<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$rules = [
    '@PSR12' => true,
    '@PSR12:risky' => true,
    '@Symfony' => true,
    '@Symfony:risky' => true,
    'array_syntax' => ['syntax' => 'short'],
    'binary_operator_spaces' => [
        'operators' => ['=>' => 'align_single_space_minimal_by_indent'],
    ],
    'blank_line_before_statement' => [
        'statements' => ['return', 'try', 'case', 'continue', 'declare', 'exit'],
    ],
    'class_attributes_separation' => [
        'elements' => ['method' => 'one', 'property' => 'one'],
    ],
    'concat_space' => ['spacing' => 'one'],
    'declare_strict_types' => true,
    'fully_qualified_strict_types' => true,
    'global_namespace_import' => [
        'import_constants' => true,
        'import_functions' => true,
        'import_classes' => true,
    ],
    'heredoc_indentation' => true,
    'list_syntax' => ['syntax' => 'short'],
    'native_function_invocation' => [
        'include' => ['@compiler_optimized', '@internal', '@all'],
        'scope' => 'namespaced',
        'strict' => true,
    ],
    'no_unused_imports' => true,
    'not_operator_with_successor_space' => true,
    'nullable_type_declaration_for_default_null_value' => true,
    'ordered_class_elements' => [
        'order' => [
            'use_trait',
            'public',
            'protected',
            'private',
        ],
    ],
    'ordered_imports' => ['sort_algorithm' => 'alpha'],
    'php_unit_method_casing' => ['case' => 'camel_case'],
    'php_unit_strict' => true,
    'phpdoc_align' => ['align' => 'left'],
    'phpdoc_line_span' => [
        'const' => 'single',
        'property' => 'single',
        'method' => 'multi',
    ],
    'phpdoc_trim_consecutive_blank_line_separation' => true,
    'single_import_per_statement' => true,
    'single_line_comment_style' => ['comment_types' => ['hash']],
    'single_quote' => true,
    'types_spaces' => ['space' => 'single'],
];

$finder = Finder::create()
    ->in([__DIR__ . '/app', __DIR__ . '/config', __DIR__ . '/database', __DIR__ . '/routes', __DIR__ . '/tests'])
    ->name('*.php')
    ->ignoreVCS(true)
    ->ignoreDotFiles(true)
    ->ignoreUnreadableDirs();

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules($rules)
    ->setFinder($finder)
    ->setUsingCache(true);
