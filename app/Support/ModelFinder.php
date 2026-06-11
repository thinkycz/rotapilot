<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Centralised "find a model by id or abort 404" helper.
 *
 * Replaces the 5-line preamble
 *
 *     $row = Model::query()->getQuery()->getQuery()->where('id', $id)->first();
 *     if ($row === null) { \abort(404); }
 *     $model = Db::hydrateOne($row, Model::class);
 *     if ($model === null) { \abort(404); }
 *
 * with a single `Model::findByIdOrAbort($id)`.
 */
final class ModelFinder
{
    /**
     * Find a model by id or abort with a 404.
     *
     * @template TModel of Model
     *
     * @param class-string<TModel> $modelClass
     */
    public static function findOrAbort(string $modelClass, int $id): Model
    {
        $model = $modelClass::query()->find($id);
        if (!$model instanceof Model) {
            \abort(404);
        }

        return $model;
    }
}
