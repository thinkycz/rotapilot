<?php

declare(strict_types=1);

\arch('app models extend BaseModel or BaseUser', function (): void {
    foreach (\glob(\base_path('app/Models/*.php')) as $file) {
        $contents = (string) \file_get_contents($file);

        \expect($contents)
            ->toMatch('/extends\\s+(BaseModel|BaseUser)/');
    }
});

\arch('non-User app models have querySelect and scopeSearch', function (): void {
    $pivots = ['StoreManagerStore', 'EmployeeStore'];

    foreach (\glob(\base_path('app/Models/*.php')) as $file) {
        $contents = (string) \file_get_contents($file);

        if (\str_contains($contents, 'extends BaseUser')) {
            continue;
        }

        // Pivot models are join tables used only through their
        // relations; neither the static querySelect() nor scopeSearch()
        // shape fits a 2-column join (the scope would have to be on
        // the FK pair, not the surrogate id). Skip them.
        $className = (string) \pathinfo($file, \PATHINFO_FILENAME);
        if (\in_array($className, $pivots, true)) {
            continue;
        }

        \expect($contents)
            ->toMatch('/public static function querySelect\\(/')
            ->toMatch('/public static function scopeSearch\\(/');
    }
});

\arch('non-Category app models have a casts() method', function (): void {
    foreach (\glob(\base_path('app/Models/*.php')) as $file) {
        $contents = (string) \file_get_contents($file);

        if (\str_contains($contents, 'class Category')) {
            continue;
        }

        \expect($contents)
            ->toMatch('/protected function casts\\(\\): array/');
    }
});
