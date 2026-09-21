<?php

// declare(strict_types=1);

namespace Base;

/**
 * Small query object used for eager loading.
 *
 * Example:
 *
 *     $users = User::with('files')->get();
 *
 * The query object intentionally does not replace Model's
 * existing query API.
 */
class ModelQuery
{
    protected string $model;

    protected array $relationships;

    public function __construct(
        string $model,
        array $relationships
    ) {
        if (!is_a($model, Model::class, true)) {
            throw new \InvalidArgumentException(
                'ModelQuery model must extend ' . Model::class . '.'
            );
        }

        foreach ($relationships as $relationship) {
            if (!is_string($relationship)) {
                throw new \InvalidArgumentException(
                    'Relationship names must be strings.'
                );
            }
        }

        $this->model = $model;
        $this->relationships = $relationships;
    }

    /**
     * Retrieve the models and eager-load the requested relationships.
     */
    public function get(
        array $wheres = [],
        array|string|null $orderBy = null,
        int $limit = -1,
    ): array
    {
        $model = $this->model;

        /*
         * Important:
         *
         * We deliberately call the existing Model::get()
         * contract here instead of introducing another query
         * implementation.
         */
        $models = $model::get(wheres: $wheres, orderBy: $orderBy, limit: $limit);

        return $model::load(
            $models,
            $this->relationships
        );
    }

     /**
     * Retrieve the models and eager-load the requested relationships.
     */
    public function first(
        array $wheres = [],
        array|string|null $orderBy = null,
    ): mixed
    {
        $model = $this->model;

        /*
         * Important:
         *
         * We deliberately call the existing Model::get()
         * contract here instead of introducing another query
         * implementation.
         */
        $model = $model::first(wheres: $wheres, orderBy: $orderBy);
        if(!$model) return null;

        return $model::load(
            [$model],
            $this->relationships
        )[0] ?? null;
    }
}