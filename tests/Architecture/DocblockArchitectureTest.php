<?php

declare(strict_types=1);

\arch('app classes have all methods documented (replaces FunctionRequired)', function (): void {
    \expect('App')
        ->toHaveMethodsDocumented();
});

\arch('app classes have all properties documented (replaces PropertyRequired)', function (): void {
    \expect('App')
        ->toHavePropertiesDocumented();
});

\arch('core package classes have all methods documented', function (): void {
    \expect('Thinkycz\\LaravelCore')
        ->toHaveMethodsDocumented();
});

\arch('core package classes have all properties documented', function (): void {
    \expect('Thinkycz\\LaravelCore')
        ->toHavePropertiesDocumented();
});

/*
 * Eloquent attributes are read through explicit getters that use
 * `assertString`, `assertInt`, etc. PHPDoc `@property` / `@method` /
 * `@phpstan-method` is forbidden on app models because it masks real
 * API gaps.
 */
\arch('app models do not declare @property, @method, or @phpstan-method', function (): void {
    $forbidden = [
        '@property',
        '@method',
        '@phpstan-method',
    ];

    foreach (\arch_php_files(\base_path('app/Models')) as $file) {
        $contents = (string) \file_get_contents($file);

        foreach ($forbidden as $tag) {
            \expect($contents)
                ->not->toContain($tag);
        }
    }
});
