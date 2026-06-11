<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * Static-typed helpers for working with raw query results.
 */
class Db
{
    /**
     * Hydrate QueryBuilder rows into an Eloquent Collection.
     *
     * @template T of Model
     *
     * @param iterable<int, object> $rows
     * @param class-string<T> $modelClass
     *
     * @return EloquentCollection<int, T>
     */
    public static function hydrate(iterable $rows, string $modelClass): EloquentCollection
    {
        $models = [];
        foreach ($rows as $row) {
            $model = new $modelClass();
            \assert($model instanceof Model);
            $built = $model->newFromBuilder(self::rowAttrs($row));
            $models[] = $built;
        }

        return new EloquentCollection($models);
    }

    /**
     * Hydrate a single row into a model.
     *
     * @template T of Model
     *
     * @param class-string<T> $modelClass
     *
     * @return T|null
     */
    public static function hydrateOne(object|null $row, string $modelClass): Model|null
    {
        if ($row === null) {
            return null;
        }

        $model = new $modelClass();
        \assert($model instanceof Model);

        return $model->newFromBuilder(self::rowAttrs($row));
    }

    /**
     * Cast a row to the array shape newFromBuilder expects.
     *
     * @return array<string, mixed>
     */
    public static function rowAttrs(object $row): array
    {
        $attrs = (array) $row;
        $out = [];
        foreach ($attrs as $k => $v) {
            $out[(string) $k] = $v;
        }

        return $out;
    }
}
