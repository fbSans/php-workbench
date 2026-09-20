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
    public function get(): array
    {
        $model = $this->model;

        /*
         * Important:
         *
         * We deliberately call the existing Model::get()
         * contract here instead of introducing another query
         * implementation.
         */
        $models = $model::get();

        return $model::load(
            $models,
            $this->relationships
        );
    }
}