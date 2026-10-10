<?php

declare(strict_types=1);

namespace Base;

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

    public function get(): array|Model|null
    {
        return $this->parent->loadRelationship(
            $this->name,
            $this->definition
        );
    }

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

        $related = $this->definition['model'];
        $type = $this->definition['type'];
        $local = $this->definition['local'];
        $foreign = $this->definition['foreign'];

        if (!is_string($related)) {
            throw new \InvalidArgumentException(
                'Relationship model must be a class name.'
            );
        }

        if (!is_string($type)) {
            throw new \InvalidArgumentException(
                'Relationship type must be a string.'
            );
        }

        if (!is_string($local) || !is_string($foreign)) {
            throw new \InvalidArgumentException(
                'Relationship keys must be strings.'
            );
        }

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

        if ($type === 'hasOne') {
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

            $models = $related::get(
                [
                    $foreign => $value,
                ],
                null,
                null,
                [],
                null
            );

            if ($models === []) {
                return [
                    'items' => [],
                    'page' => $page,
                    'page_size' => $pageSize,
                    'total' => 0,
                    'pages' => 0,
                ];
            }

            return [
                'items' => [
                    $models[0],
                ],
                'page' => 1,
                'page_size' => $pageSize,
                'total' => 1,
                'pages' => 1,
            ];
        }

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