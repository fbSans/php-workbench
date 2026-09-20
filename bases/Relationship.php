<?php

// declare(strict_types=1);

namespace Base;

/**
 * Represents a relationship belonging to a model.
 *
 * Examples:
 *
 *     $user->files()->get();
 *
 *     $page = 1;
 *     $user->files()->fetchPage($page, 20);
 *
 * Supported relationships:
 *
 * - hasMany
 * - belongsTo
 */
class Relationship
{
    protected Model $parent;

    protected string $name;

    protected array $definition;

    public function __construct(
        Model $parent,
        string $name,
        array $definition
    ) {
        $this->parent = $parent;
        $this->name = $name;
        $this->definition = $definition;
    }

    /**
     * Retrieve all related models.
     *
     * This preserves the relationship API:
     *
     *     $user->files()->get();
     *
     * The relationship is cached by loadRelationship().
     */
    public function get(): array|Model|null
    {
        return $this->parent->loadRelationship(
            $this->name,
            $this->definition
        );
    }

    /**
     * Retrieve a page of related models.
     *
     * The result has the same structure as Model::fetchPage().
     */
    public function fetchPage(
        int &$page,
        int $pageSize
    ): array {
        if ($page < 1) {
            $page = 1;
        }

        if ($pageSize < 1) {
            throw new \InvalidArgumentException(
                'Page size must be greater than zero.'
            );
        }

        /*
         * Validate the definition before accessing offsets.
         *
         * This is important because array offset access on an
         * unexpected value is what was causing the fatal error.
         */
        if (!isset($this->definition['model'])) {
            throw new \InvalidArgumentException(
                "Relationship '{$this->name}' must define a model."
            );
        }

        if (!is_string($this->definition['model'])) {
            throw new \InvalidArgumentException(
                'Relationship model must be a class name.'
            );
        }

        if (!isset($this->definition['type'])) {
            throw new \InvalidArgumentException(
                "Relationship '{$this->name}' must define a type."
            );
        }

        if (!is_string($this->definition['type'])) {
            throw new \InvalidArgumentException(
                'Relationship type must be a string.'
            );
        }

        if (!isset($this->definition['local'])) {
            throw new \InvalidArgumentException(
                "Relationship '{$this->name}' must define a local key."
            );
        }

        if (!isset($this->definition['foreign'])) {
            throw new \InvalidArgumentException(
                "Relationship '{$this->name}' must define a foreign key."
            );
        }

        if (
            !is_string($this->definition['local']) ||
            !is_string($this->definition['foreign'])
        ) {
            throw new \InvalidArgumentException(
                'Relationship keys must be strings.'
            );
        }

        $related = $this->definition['model'];
        $type = $this->definition['type'];
        $local = $this->definition['local'];
        $foreign = $this->definition['foreign'];

        if (!class_exists($related)) {
            throw new \InvalidArgumentException(
                "Relationship '{$this->name}' references "
                . "an invalid model."
            );
        }

        if (!is_a($related, Model::class, true)) {
            throw new \InvalidArgumentException(
                'Relationship model must extend ' . Model::class . '.'
            );
        }

        /*
         * hasMany:
         *
         * parent.local = related.foreign
         */
        if ($type === 'hasMany') {
            $value = $this->parent->{$local};

            if ($value === null) {
                return [
                    'items' => [],
                    'page' => $page,
                    'page_size' => $pageSize,
                    'total' => 0,
                    'pages' => 0,
                ];
            }

            return $related::fetchPage(
                $page,
                $pageSize,
                [
                    $foreign => $value,
                ]
            );
        }

        /*
         * belongsTo:
         *
         * parent.foreign = related.local
         */
        if ($type === 'belongsTo') {
            $value = $this->parent->{$foreign};

            if ($value === null) {
                return [
                    'items' => [],
                    'page' => $page,
                    'page_size' => $pageSize,
                    'total' => 0,
                    'pages' => 0,
                ];
            }

            return $related::fetchPage(
                $page,
                $pageSize,
                [
                    $local => $value,
                ]
            );
        }

        throw new \LogicException(
            "Unsupported relationship type '{$type}'."
        );
    }
}