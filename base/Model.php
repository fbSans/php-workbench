<?php

declare(strict_types=1);

namespace Base;

use PDO;

/**
 * Base model for database-backed entities.
 *
 * Provides:
 *
 * - Attribute access and hydration.
 * - Basic CRUD operations.
 * - SELECT helpers.
 * - Pagination.
 * - WHERE, GROUP BY, HAVING and ORDER BY support.
 * - Explicit JOIN support.
 * - hasMany and belongsTo relationships.
 * - Lazy relationship loading.
 * - Eager relationship loading.
 */
abstract class Model
{
    /**
     * Database table associated with the model.
     *
     * Child models should override this constant:
     *
     * protected const table = 'users';
     */
    protected const table = null;

    /**
     * Primary key.
     *
     * Simple key:
     *
     * protected const primary_key = 'id';
     *
     * Compound key:
     *
     * protected const primary_key = [
     *     'company_id',
     *     'employee_id',
     * ];
     */
    protected const primary_key = 'id';

    /**
     * Attributes allowed during persistence.
     */
    protected array $fillable = [];

    /**
     * Attributes excluded by toArray().
     */
    protected array $hidden = [];

    /**
     * Model attributes.
     */
    protected array $attributes = [];

    /**
     * Primary key values currently belonging to this model.
     */
    protected array $primaryKeyValues = [];

    /**
     * Relationship definitions.
     */
    protected array $relationships = [];

    /**
     * Already-loaded relationships.
     */
    protected array $loadedRelationships = [];

    /**
     * Shared database connection.
     */
    protected static ?PDO $pdo = null;

    /*
     * =========================================================================
     * CONNECTION
     * =========================================================================
     */

    public static function setConnection(PDO $pdo): void
    {
        static::$pdo = $pdo;
    }

    public static function closeConnection(): void
    {
        static::$pdo = null;
    }

    protected static function connection(): PDO
    {
        if (static::$pdo === null) {
            throw new \RuntimeException(
                'Database connection has not been initialized.'
            );
        }

        return static::$pdo;
    }

    /*
     * =========================================================================
     * MODEL / ATTRIBUTES
     * =========================================================================
     */

    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }

        $this->refreshPrimaryKeyValues();
    }

    public function __set(
        string $name,
        mixed $value
    ): void {
        if (
            $this->fillable !== [] &&
            !in_array($name, $this->fillable, true)
        ) {
            throw new \InvalidArgumentException(
                "Attribute '{$name}' is not fillable."
            );
        }

        $this->attributes[$name] = $value;

        $this->refreshPrimaryKeyValues();
    }

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        if (array_key_exists($name, $this->loadedRelationships)) {
            return $this->loadedRelationships[$name];
        }

        if (array_key_exists($name, $this->relationships)) {
            return $this->loadRelationship($name);
        }

        return null;
    }

    public function __isset(string $name): bool
    {
        if (array_key_exists($name, $this->attributes)) {
            return true;
        }

        if (array_key_exists($name, $this->loadedRelationships)) {
            return true;
        }

        return array_key_exists($name, $this->relationships);
    }

    /**
     * Resolve a relationship as a Relationship object.
     *
     * Example:
     *
     * $user->files()->get();
     * $user->files()->fetchPage($page, 20);
     */
    public function __call(
        string $name,
        array $arguments
    ): mixed {
        if (array_key_exists($name, $this->relationships)) {
            if ($arguments !== []) {
                throw new \ArgumentCountError(
                    "Relationship '{$name}' does not accept arguments."
                );
            }

            return new Relationship(
                $this,
                $name,
                $this->relationships[$name]
            );
        }

        throw new \BadMethodCallException(
            'Call to undefined method '
                . static::class
                . "::{$name}()"
        );
    }

    public function toArray(bool $include_hidden = false): array
    {
        $result = [];

        foreach ($this->attributes as $key => $value) {
            if (!$include_hidden && in_array($key, $this->hidden, true)) {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Get the model table.
     *
     * static::table uses late static binding, therefore a child
     * model's table constant is resolved here.
     */
    public static function table(): string
    {
        $table = static::table;

        if (!is_string($table) || $table === '') {
            throw new \LogicException(
                static::class
                    . ' must define a non-empty string table.'
            );
        }

        static::validateIdentifier($table);

        return $table;
    }

    /*
     * =========================================================================
     * RELATIONSHIPS
     * =========================================================================
     */

    public static function with(
        array|string $relationships
    ): ModelQuery {
        $relationships = is_array($relationships)
            ? $relationships
            : [$relationships];

        foreach ($relationships as $name) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException(
                    'Relationship names must be strings.'
                );
            }
        }

        return new ModelQuery(
            static::class,
            $relationships
        );
    }

    /**
     * Eager-load relationships on an existing collection.
     */
    public static function load(
        array $models,
        array|string $relationships
    ): array {
        if ($models === []) {
            return $models;
        }

        $relationships = is_array($relationships)
            ? $relationships
            : [$relationships];

        foreach ($relationships as $name) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException(
                    'Relationship names must be strings.'
                );
            }

            static::eagerLoadRelationship(
                $models,
                $name
            );
        }

        return $models;
    }

    protected static function eagerLoadRelationship(
        array $models,
        string $name
    ): void {
        $instance = new static();

        if (!array_key_exists($name, $instance->relationships)) {
            throw new \InvalidArgumentException(
                "Relationship '{$name}' does not exist."
            );
        }

        $definition = $instance->relationships[$name];

        if (!is_array($definition)) {
            throw new \InvalidArgumentException(
                "Relationship '{$name}' does not have a valid definition."
            );
        }

        static::validateRelationship(
            $name,
            $definition
        );

        $related = $definition['model'];
        $type = $definition['type'];
        $local = $definition['local'];
        $foreign = $definition['foreign'];

        if ($type === 'hasMany') {
            static::eagerLoadHasMany(
                $models,
                $name,
                $related,
                $local,
                $foreign
            );

            return;
        }

        if ($type === 'hasOne') {
            static::eagerLoadHasOne(
                $models,
                $name,
                $related,
                $local,
                $foreign
            );

            return;
        }

        static::eagerLoadBelongsTo(
            $models,
            $name,
            $related,
            $local,
            $foreign
        );
    }

    protected static function eagerLoadHasMany(
        array $models,
        string $name,
        string $related,
        string $local,
        string $foreign
    ): void {
        $values = [];

        foreach ($models as $model) {
            if (!$model instanceof static) {
                throw new \InvalidArgumentException(
                    'Eager-loaded collection contains an invalid model.'
                );
            }

            $value = $model->{$local};

            if ($value !== null) {
                $values[(string) $value] = $value;
            }
        }

        if ($values === []) {
            foreach ($models as $model) {
                $model->loadedRelationships[$name] = [];
            }

            return;
        }

        $relatedModels = $related::getWhereIn(
            $foreign,
            array_values($values)
        );

        $grouped = [];

        foreach ($relatedModels as $relatedModel) {
            $value = $relatedModel->{$foreign};

            if ($value !== null) {
                $grouped[(string) $value][] = $relatedModel;
            }
        }

        foreach ($models as $model) {
            $value = $model->{$local};

            if ($value === null) {
                $model->loadedRelationships[$name] = [];
                continue;
            }

            $model->loadedRelationships[$name] =
                $grouped[(string) $value] ?? [];
        }
    }

    protected static function eagerLoadHasOne(
        array $models,
        string $name,
        string $related,
        string $local,
        string $foreign
    ): void {
        $values = [];

        foreach ($models as $model) {
            if (!$model instanceof static) {
                throw new \InvalidArgumentException(
                    'Eager-loaded collection contains an invalid model.'
                );
            }

            $value = $model->{$local};

            if ($value !== null) {
                $values[(string) $value] = $value;
            }
        }

        if ($values === []) {
            foreach ($models as $model) {
                $model->loadedRelationships[$name] = null;
            }

            return;
        }

        $relatedModels = $related::getWhereIn(
            $foreign,
            array_values($values)
        );

        $indexed = [];

        foreach ($relatedModels as $relatedModel) {
            $value = $relatedModel->{$foreign};

            if ($value !== null) {
                $indexed[(string) $value] = $relatedModel;
            }
        }

        foreach ($models as $model) {
            $value = $model->{$local};

            if ($value === null) {
                $model->loadedRelationships[$name] = null;
                continue;
            }

            $model->loadedRelationships[$name] =
                $indexed[(string) $value] ?? null;
        }
    }

    protected static function eagerLoadBelongsTo(
        array $models,
        string $name,
        string $related,
        string $local,
        string $foreign
    ): void {
        $values = [];

        foreach ($models as $model) {
            if (!$model instanceof static) {
                throw new \InvalidArgumentException(
                    'Eager-loaded collection contains an invalid model.'
                );
            }

            $value = $model->{$foreign};

            if ($value !== null) {
                $values[(string) $value] = $value;
            }
        }

        if ($values === []) {
            foreach ($models as $model) {
                $model->loadedRelationships[$name] = null;
            }

            return;
        }

        $relatedModels = $related::getWhereIn(
            $local,
            array_values($values)
        );

        $indexed = [];

        foreach ($relatedModels as $relatedModel) {
            $value = $relatedModel->{$local};

            if ($value !== null) {
                $indexed[(string) $value] = $relatedModel;
            }
        }

        foreach ($models as $model) {
            $value = $model->{$foreign};

            if ($value === null) {
                $model->loadedRelationships[$name] = null;
                continue;
            }

            $model->loadedRelationships[$name] =
                $indexed[(string) $value] ?? null;
        }
    }

    /**
     * Validate a relationship definition before consuming it.
     */
    protected static function validateRelationship(
        string $name,
        array $definition
    ): void {
        if (!array_key_exists('model', $definition)) {
            throw new \InvalidArgumentException(
                "Relationship '{$name}' must define a model."
            );
        }

        $model = $definition['model'];

        if (!is_string($model) || $model === '') {
            throw new \InvalidArgumentException(
                "Relationship '{$name}' model must be a class name."
            );
        }

        if (!class_exists($model)) {
            throw new \InvalidArgumentException(
                "Relationship '{$name}' references an invalid model."
            );
        }

        if (!is_a($model, self::class, true)) {
            throw new \InvalidArgumentException(
                "Relationship '{$name}' model must extend "
                    . self::class . '.'
            );
        }

        if (!array_key_exists('type', $definition)) {
            throw new \InvalidArgumentException(
                "Relationship '{$name}' must define a type."
            );
        }

        $type = $definition['type'];

        if (
            !is_string($type) ||
            !in_array(
                $type,
                ['hasMany', 'hasOne', 'belongsTo'],
                true
            )
        ) {
            throw new \InvalidArgumentException(
                "Relationship '{$name}' has an invalid type."
            );
        }

        if (!array_key_exists('local', $definition)) {
            throw new \InvalidArgumentException(
                "Relationship '{$name}' must define a local key."
            );
        }

        if (!array_key_exists('foreign', $definition)) {
            throw new \InvalidArgumentException(
                "Relationship '{$name}' must define a foreign key."
            );
        }

        $local = $definition['local'];
        $foreign = $definition['foreign'];

        if (!is_string($local) || !is_string($foreign)) {
            throw new \InvalidArgumentException(
                "Relationship '{$name}' keys must be strings."
            );
        }

        static::validateColumn($local);
        static::validateColumn($foreign);
    }

    public function loadRelationship(
        string $name,
        ?array $definition = null
    ): mixed {
        if (array_key_exists($name, $this->loadedRelationships)) {
            return $this->loadedRelationships[$name];
        }

        if ($definition === null) {
            if (!array_key_exists($name, $this->relationships)) {
                throw new \InvalidArgumentException(
                    "Relationship '{$name}' does not exist."
                );
            }

            $definition = $this->relationships[$name];
        }

        if (!is_array($definition)) {
            throw new \InvalidArgumentException(
                "Relationship '{$name}' does not have a valid definition."
            );
        }

        static::validateRelationship(
            $name,
            $definition
        );

        $related = $definition['model'];
        $type = $definition['type'];
        $local = $definition['local'];
        $foreign = $definition['foreign'];

        if ($type === 'hasMany') {
            $value = $this->{$local};

            if ($value === null) {
                return $this->loadedRelationships[$name] = [];
            }

            return $this->loadedRelationships[$name] =
                $related::get([
                    $foreign => $value,
                ]);
        }

        if ($type === 'hasOne') {
            $value = $this->{$local};

            if ($value === null) {
                return $this->loadedRelationships[$name] = null;
            }

            $models = $related::get([
                $foreign => $value,
            ]);

            return $this->loadedRelationships[$name] =
                $models[0] ?? null;
        }

        // belongsTo

        $value = $this->{$foreign};

        if ($value === null) {
            return $this->loadedRelationships[$name] = null;
        }

        $result = $related::get([
            $local => $value,
        ]);

        return $this->loadedRelationships[$name] =
            $result[0] ?? null;
    }

    /*
     * =========================================================================
     * FETCHING
     * =========================================================================
     */

    public static function fetchPage(
        int &$page,
        int $pageSize,
        array $wheres = [],
        array|string|null $select = null,
        array|string|null $groupBy = null,
        array $having = [],
        array|string|null $orderBy = null,
        array $joins = []
    ): array {
        if ($page < 1) {
            $page = 1;
        }

        if ($pageSize < 1) {
            throw new \InvalidArgumentException(
                'Page size must be greater than zero.'
            );
        }

        $table = static::table();

        $select = static::prepareSelect(
            $select,
            $joins
        );

        $selectSql = static::buildSelect($select);

        $joinData = static::buildJoins($joins);
        $whereData = static::buildWhere($wheres);
        $groupData = static::buildGroupBy($groupBy);
        $havingData = static::buildHaving($having);

        $orderSql = static::buildOrderBy(
            $orderBy,
            !empty($joins)
        );

        $offset = ($page - 1) * $pageSize;

        if ($groupData['sql'] !== '') {
            $countSql = "
                SELECT COUNT(*)
                FROM (
                    SELECT {$groupData['columns']}
                    FROM {$table}
                    {$joinData['sql']}
                    {$whereData['sql']}
                    {$groupData['sql']}
                    {$havingData['sql']}
                ) AS count_table
            ";
        } else {
            $countSql = "
                SELECT COUNT(*)
                FROM {$table}
                {$joinData['sql']}
                {$whereData['sql']}
            ";
        }

        $countStmt = static::connection()->prepare(
            $countSql
        );

        $countStmt->execute(
            array_merge(
                $joinData['params'],
                $whereData['params'],
                $havingData['params']
            )
        );

        $total = (int) $countStmt->fetchColumn();

        $pages = $total === 0
            ? 0
            : (int) ceil($total / $pageSize);

        if ($pages > 0 && $page > $pages) {
            $page = $pages;
            $offset = ($page - 1) * $pageSize;
        }

        $sql = "
            SELECT {$selectSql}
            FROM {$table}
            {$joinData['sql']}
            {$whereData['sql']}
            {$groupData['sql']}
            {$havingData['sql']}
            {$orderSql}
            LIMIT {$pageSize}
            OFFSET {$offset}
        ";

        $params = array_merge(
            $joinData['params'],
            $whereData['params'],
            $havingData['params']
        );

        $stmt = static::connection()->prepare($sql);

        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (static::isAggregateSelect($select)) {
            $items = $rows;
        } else {
            $items = [];

            foreach ($rows as $row) {
                $items[] = static::hydrate($row);
            }
        }

        return [
            'items' => $items,
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'pages' => $pages,
        ];
    }

    public static function find(mixed $id): ?static
    {
        $primaryKey = static::primaryKeyDefinition();

        if (is_array($primaryKey)) {
            if (!is_array($id)) {
                throw new \InvalidArgumentException(
                    'Compound primary key requires an array of values.'
                );
            }

            $wheres = [];

            foreach ($primaryKey as $column) {
                if (!array_key_exists($column, $id)) {
                    throw new \InvalidArgumentException(
                        "Missing primary key value for '{$column}'."
                    );
                }

                $wheres[$column] = $id[$column];
            }
        } else {
            $wheres = [
                $primaryKey => $id,
            ];
        }

        $models = static::get($wheres);

        return $models[0] ?? null;
    }

    /**
     * Retrieve all matching records, unless limit is imposed.
     *
     *
     */
    public static function get(
        array $wheres = [],
        array|string|null $select = null,
        array|string|null $groupBy = null,
        array $having = [],
        array|string|null $orderBy = null,
        array $joins = [],
        int $limit = -1,
    ): array {
        $table = static::table();

        $select = static::prepareSelect(
            $select,
            $joins
        );

        $selectSql = static::buildSelect($select);

        $joinData = static::buildJoins($joins);
        $whereData = static::buildWhere($wheres);
        $groupData = static::buildGroupBy($groupBy);
        $havingData = static::buildHaving($having);
        $limitData = $limit > 0 ? "LIMIT {$limit} "  : '';

        $orderSql = static::buildOrderBy(
            $orderBy,
            !empty($joins)
        );

        $sql = "
            SELECT {$selectSql}
            FROM {$table}
            {$joinData['sql']}
            {$whereData['sql']}
            {$groupData['sql']}
            {$havingData['sql']}
            {$orderSql}
            {$limitData}
        ";

        $params = array_merge(
            $joinData['params'],
            $whereData['params'],
            $havingData['params']
        );

        $stmt = static::connection()->prepare($sql);

        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (static::isAggregateSelect($select)) {
            return $rows;
        }

        $models = [];

        foreach ($rows as $row) {
            $models[] = static::hydrate($row);
        }

        return $models;
    }

    public static function first( 
        array $wheres = [],
        array|string|null $select = null,
        array|string|null $groupBy = null,
        array $having = [],
        array|string|null $orderBy = null,
        array $joins = [],
    )
    {
        return static::get($wheres, $select, $groupBy, $having, $orderBy, $joins, limit: 1)[0] ?? null;
    }

    public static function getWhereIn(
        string $column,
        array $values
    ): array {
        if ($values === []) {
            return [];
        }

        static::validateColumn($column);

        $placeholders = implode(
            ', ',
            array_fill(0, count($values), '?')
        );

        $table = static::table();

        $sql = "
            SELECT {$table}.*
            FROM {$table}
            WHERE {$table}.{$column}
            IN ({$placeholders})
        ";

        $stmt = static::connection()->prepare($sql);

        $stmt->execute(
            array_values($values)
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $models = [];

        foreach ($rows as $row) {
            $models[] = static::hydrate($row);
        }

        return $models;
    }

    /*
     * =========================================================================
     * PERSISTENCE
     * =========================================================================
     */

    public function create(): static
    {
        $columns = [];
        $values = [];

        foreach ($this->attributes as $column => $value) {
            if (
                $this->fillable !== [] &&
                !in_array($column, $this->fillable, true)
            ) {
                continue;
            }

            if (!is_string($column)) {
                throw new \InvalidArgumentException(
                    'Model attribute names must be strings.'
                );
            }

            static::validateColumn($column);

            $columns[] = $column;
            $values[] = $value;
        }

        if ($columns === []) {
            throw new \InvalidArgumentException(
                'No attributes available for insertion.'
            );
        }

        $table = static::table();

        $columnSql = implode(
            ', ',
            $columns
        );

        $placeholders = implode(
            ', ',
            array_fill(
                0,
                count($values),
                '?'
            )
        );

        $sql = "
            INSERT INTO {$table}
            ({$columnSql})
            VALUES ({$placeholders})
        ";

        $stmt = static::connection()->prepare($sql);

        $stmt->execute($values);

        $primaryKey = static::primaryKeyDefinition();

        if (
            is_string($primaryKey) &&
            !array_key_exists(
                $primaryKey,
                $this->attributes
            )
        ) {
            $this->attributes[$primaryKey] =
                static::connection()->lastInsertId();
        }

        $this->refreshPrimaryKeyValues();

        return $this;
    }

    public static function createOne(
        array $attributes
    ): static {
        $model = new static($attributes);

        return $model->create();
    }

    public function update(
        ?array $attributes = null
    ): static {
        if ($attributes !== null) {
            foreach ($attributes as $key => $value) {
                if (!is_string($key)) {
                    throw new \InvalidArgumentException(
                        'Model attribute names must be strings.'
                    );
                }

                $this->{$key} = $value;
            }
        }

        if ($this->primaryKeyValues === []) {
            $this->refreshPrimaryKeyValues();
        }

        if ($this->primaryKeyValues === []) {
            throw new \RuntimeException(
                'Cannot update a model without a primary key.'
            );
        }

        $sets = [];
        $values = [];

        $primaryKey = static::primaryKeyDefinition();

        foreach ($this->attributes as $column => $value) {
            if (!is_string($column)) {
                throw new \InvalidArgumentException(
                    'Model attribute names must be strings.'
                );
            }

            if (
                $this->fillable !== [] &&
                !in_array($column, $this->fillable, true)
            ) {
                continue;
            }

            if (
                is_array($primaryKey)
                ? in_array($column, $primaryKey, true)
                : $column === $primaryKey
            ) {
                continue;
            }

            static::validateColumn($column);

            $sets[] = "{$column} = ?";
            $values[] = $value;
        }

        if ($sets === []) {
            return $this;
        }

        $where = static::buildPkWhere(
            $this->primaryKeyValues
        );

        $sql = "
            UPDATE " . static::table() . "
            SET " . implode(', ', $sets) . "
            {$where['sql']}
        ";

        $stmt = static::connection()->prepare($sql);

        $stmt->execute(
            array_merge(
                $values,
                $where['params']
            )
        );

        return $this;
    }

    public function delete(): bool
    {
        if ($this->primaryKeyValues === []) {
            $this->refreshPrimaryKeyValues();
        }

        if ($this->primaryKeyValues === []) {
            throw new \RuntimeException(
                'Cannot delete a model without a primary key.'
            );
        }

        $where = static::buildPkWhere(
            $this->primaryKeyValues
        );

        $sql = "
            DELETE FROM " . static::table() . "
            {$where['sql']}
        ";

        $stmt = static::connection()->prepare($sql);

        return $stmt->execute(
            $where['params']
        );
    }

    /*
     * =========================================================================
     * HYDRATION
     * =========================================================================
     */

    protected static function hydrate(
        array $row
    ): static {
        $model = new static();

        $model->attributes = $row;

        $model->refreshPrimaryKeyValues();

        return $model;
    }

    protected function refreshPrimaryKeyValues(): void
    {
        $this->primaryKeyValues = [];

        $primaryKey = static::primaryKeyDefinition();

        if (is_array($primaryKey)) {
            foreach ($primaryKey as $column) {
                if (
                    array_key_exists(
                        $column,
                        $this->attributes
                    )
                ) {
                    $this->primaryKeyValues[$column] =
                        $this->attributes[$column];
                }
            }

            return;
        }

        if (
            array_key_exists(
                $primaryKey,
                $this->attributes
            )
        ) {
            $this->primaryKeyValues[$primaryKey] =
                $this->attributes[$primaryKey];
        }
    }

    /*
     * =========================================================================
     * WHERE
     * =========================================================================
     */

    protected static function buildWhere(
        array $wheres
    ): array {
        if ($wheres === []) {
            return [
                'sql' => '',
                'params' => [],
            ];
        }

        $conditions = [];
        $params = [];

        foreach ($wheres as $column => $value) {
            if (!is_string($column)) {
                throw new \InvalidArgumentException(
                    'WHERE column names must be strings.'
                );
            }

            static::validateColumn($column);

            if (
                is_array($value) &&
                count($value) === 2 &&
                array_key_exists(0, $value) &&
                is_string($value[0])
            ) {
                $operator = strtoupper(
                    trim($value[0])
                );

                $operator = match ($operator) {
                    '=', '==', '!=', '<>', '<',
                    '<=', '>', '>='
                    => $operator,

                    'LIKE', 'NOT LIKE',
                    'IN', 'NOT IN',
                    'IS', 'IS NOT'
                    => $operator,

                    default => throw new \InvalidArgumentException(
                        "Invalid WHERE operator '{$operator}'."
                    ),
                };

                $operand = $value[1];

                if (
                    in_array(
                        $operator,
                        ['IS', 'IS NOT'],
                        true
                    )
                ) {
                    if ($operand !== null) {
                        throw new \InvalidArgumentException(
                            "{$operator} only accepts NULL."
                        );
                    }

                    $conditions[] =
                        "{$column} {$operator} NULL";

                    continue;
                }

                if (
                    in_array(
                        $operator,
                        ['IN', 'NOT IN'],
                        true
                    )
                ) {
                    if (!is_array($operand)) {
                        throw new \InvalidArgumentException(
                            "{$operator} requires an array."
                        );
                    }

                    if ($operand === []) {
                        $conditions[] =
                            $operator === 'IN'
                            ? '0'
                            : '1';

                        continue;
                    }

                    $placeholders = implode(
                        ', ',
                        array_fill(
                            0,
                            count($operand),
                            '?'
                        )
                    );

                    $conditions[] =
                        "{$column} {$operator} ({$placeholders})";

                    foreach ($operand as $item) {
                        $params[] = $item;
                    }

                    continue;
                }

                if ($operand === null) {
                    $conditions[] =
                        $operator === '!=' ||
                        $operator === '<>'
                        ? "{$column} IS NOT NULL"
                        : "{$column} IS NULL";

                    continue;
                }

                $conditions[] =
                    "{$column} {$operator} ?";

                $params[] = $operand;

                continue;
            }

            if ($value === null) {
                $conditions[] =
                    "{$column} IS NULL";

                continue;
            }

            $conditions[] =
                "{$column} = ?";

            $params[] = $value;
        }

        return [
            'sql' => ' WHERE ' . implode(
                ' AND ',
                $conditions
            ),
            'params' => $params,
        ];
    }

    /*
     * =========================================================================
     * SELECT
     * =========================================================================
     */

    /**
     * Build the default SELECT list.
     *
     * When SELECT is omitted, the base table's columns are selected.
     *
     * JOIN definitions may optionally provide:
     *
     * 'columns' => ['name', 'email']
     */
    protected static function prepareSelect(
        array|string|null $select,
        array $joins
    ): array|string {
        if ($select !== null) {
            return $select;
        }

        $table = static::table();

        $columns = [
            "{$table}.*",
        ];

        foreach ($joins as $join) {
            if (!is_array($join)) {
                throw new \InvalidArgumentException(
                    'Each JOIN definition must be an array.'
                );
            }

            if (!array_key_exists('model', $join)) {
                throw new \InvalidArgumentException(
                    'Join must define a model.'
                );
            }

            $model = $join['model'];

            if (!is_string($model)) {
                throw new \InvalidArgumentException(
                    'Join model must be a class name.'
                );
            }

            if (!class_exists($model)) {
                throw new \InvalidArgumentException(
                    'Join model class does not exist.'
                );
            }

            if (!is_a($model, self::class, true)) {
                throw new \InvalidArgumentException(
                    'Join model must extend ' . self::class . '.'
                );
            }

            if (!array_key_exists('columns', $join)) {
                continue;
            }

            $joinColumns = $join['columns'];

            if (
                !is_string($joinColumns) &&
                !is_array($joinColumns)
            ) {
                throw new \InvalidArgumentException(
                    'JOIN columns must be a string or array.'
                );
            }

            $joinColumns = is_array($joinColumns)
                ? $joinColumns
                : [$joinColumns];

            $joinTable = $model::table();

            foreach ($joinColumns as $column) {
                if (!is_string($column)) {
                    throw new \InvalidArgumentException(
                        'JOIN columns must be strings.'
                    );
                }

                static::validateColumn($column);

                $columns[] =
                    "{$joinTable}.{$column}";
            }
        }

        return $columns;
    }

    protected static function buildSelect(
        array|string $select
    ): string {
        if (is_string($select)) {
            if (trim($select) === '') {
                throw new \InvalidArgumentException(
                    'SELECT expression cannot be empty.'
                );
            }

            return $select;
        }

        if ($select === []) {
            throw new \InvalidArgumentException(
                'SELECT list cannot be empty.'
            );
        }

        foreach ($select as $column) {
            if (!is_string($column)) {
                throw new \InvalidArgumentException(
                    'SELECT expressions must be strings.'
                );
            }
        }

        return implode(', ', $select);
    }

    protected static function isAggregateSelect(
        array|string|null $select
    ): bool {
        if ($select === null) {
            return false;
        }

        $columns = is_array($select)
            ? $select
            : [$select];

        foreach ($columns as $column) {
            if (!is_string($column)) {
                continue;
            }

            if (
                preg_match(
                    '/\b(COUNT|SUM|AVG|MIN|MAX)\s*\(/i',
                    $column
                ) === 1
            ) {
                return true;
            }
        }

        return false;
    }

    /*
     * =========================================================================
     * GROUP BY
     * =========================================================================
     */

    /**
     * Always returns:
     *
     * [
     *     'columns' => string,
     *     'sql' => string,
     * ]
     */
    protected static function buildGroupBy(
        array|string|null $groupBy
    ): array {
        if ($groupBy === null) {
            return [
                'columns' => '',
                'sql' => '',
            ];
        }

        $columns = is_array($groupBy)
            ? $groupBy
            : [$groupBy];

        if ($columns === []) {
            throw new \InvalidArgumentException(
                'GROUP BY list cannot be empty.'
            );
        }

        foreach ($columns as $column) {
            if (!is_string($column)) {
                throw new \InvalidArgumentException(
                    'GROUP BY columns must be strings.'
                );
            }

            static::validateColumn($column);
        }

        $columnsSql = implode(
            ', ',
            $columns
        );

        return [
            'columns' => $columnsSql,
            'sql' => ' GROUP BY ' . $columnsSql,
        ];
    }

    /*
     * =========================================================================
     * HAVING
     * =========================================================================
     */

    protected static function buildHaving(
        array $having
    ): array {
        if ($having === []) {
            return [
                'sql' => '',
                'params' => [],
            ];
        }

        $conditions = [];
        $params = [];

        foreach ($having as $column => $value) {
            if (!is_string($column)) {
                throw new \InvalidArgumentException(
                    'HAVING column names must be strings.'
                );
            }

            static::validateColumn($column);

            if (
                is_array($value) &&
                count($value) === 2 &&
                array_key_exists(0, $value)
            ) {
                if (!is_string($value[0])) {
                    throw new \InvalidArgumentException(
                        'HAVING operator must be a string.'
                    );
                }

                $operator = strtoupper(
                    trim($value[0])
                );

                $allowed = [
                    '=',
                    '!=',
                    '<>',
                    '<',
                    '<=',
                    '>',
                    '>=',
                    'LIKE',
                    'NOT LIKE',
                ];

                if (!in_array(
                    $operator,
                    $allowed,
                    true
                )) {
                    throw new \InvalidArgumentException(
                        "Invalid HAVING operator '{$operator}'."
                    );
                }

                $conditions[] =
                    "{$column} {$operator} ?";

                $params[] = $value[1];

                continue;
            }

            $conditions[] =
                "{$column} = ?";

            $params[] = $value;
        }

        return [
            'sql' => ' HAVING ' . implode(
                ' AND ',
                $conditions
            ),
            'params' => $params,
        ];
    }

    /*
     * =========================================================================
     * ORDER BY
     * =========================================================================
     */

    protected static function buildOrderBy(
        array|string|null $orderBy,
        bool $qualified = false
    ): string {
        if ($orderBy === null) {
            $primaryKey = static::primaryKeyDefinition();

            if (is_array($primaryKey)) {
                $primaryKey = $primaryKey[0];
            }

            $column = $qualified
                ? static::table() . '.' . $primaryKey
                : $primaryKey;

            return " ORDER BY {$column} ASC";
        }

        if (
            is_array($orderBy) &&
            $orderBy === []
        ) {
            return '';
        }

        $orders = [];

        if (is_string($orderBy)) {
            if (trim($orderBy) === '') {
                return '';
            }

            /*
             * Preserve the existing API where a string is treated
             * as an already-built ORDER BY expression.
             */
            $orders[] = $orderBy;
        } else {
            foreach ($orderBy as $column => $direction) {
                if (!is_string($column)) {
                    throw new \InvalidArgumentException(
                        'ORDER BY column names must be strings.'
                    );
                }

                static::validateColumn($column);

                if (!is_string($direction)) {
                    throw new \InvalidArgumentException(
                        'ORDER BY direction must be a string.'
                    );
                }

                $direction = strtoupper(
                    trim($direction)
                );

                if (!in_array(
                    $direction,
                    ['ASC', 'DESC'],
                    true
                )) {
                    throw new \InvalidArgumentException(
                        'ORDER BY direction must be ASC or DESC.'
                    );
                }

                $orders[] =
                    "{$column} {$direction}";
            }
        }

        if ($orders === []) {
            return '';
        }

        return ' ORDER BY ' . implode(
            ', ',
            $orders
        );
    }

    /*
     * =========================================================================
     * JOIN
     * =========================================================================
     */

    protected static function buildJoins(
        array $joins
    ): array {
        if ($joins === []) {
            return [
                'sql' => '',
                'params' => [],
            ];
        }

        $sql = '';

        foreach ($joins as $join) {
            if (!is_array($join)) {
                throw new \InvalidArgumentException(
                    'Each JOIN definition must be an array.'
                );
            }

            if (!array_key_exists('model', $join)) {
                throw new \InvalidArgumentException(
                    'Join must define a model.'
                );
            }

            $model = $join['model'];

            if (!is_string($model)) {
                throw new \InvalidArgumentException(
                    'Join model must be a class name.'
                );
            }

            if (!class_exists($model)) {
                throw new \InvalidArgumentException(
                    'Join model class does not exist.'
                );
            }

            if (!is_a($model, self::class, true)) {
                throw new \InvalidArgumentException(
                    'Join model must extend ' . self::class . '.'
                );
            }

            $type = $join['type'] ?? 'INNER';

            if (!is_string($type)) {
                throw new \InvalidArgumentException(
                    'JOIN type must be a string.'
                );
            }

            $type = strtoupper(
                trim($type)
            );

            if (!in_array(
                $type,
                ['INNER', 'LEFT', 'RIGHT'],
                true
            )) {
                throw new \InvalidArgumentException(
                    "Unsupported JOIN type '{$type}'."
                );
            }

            $local = $join['local']
                ?? static::primaryKeyDefinition();

            $foreign = $join['foreign']
                ?? $model::primaryKeyDefinition();

            if (
                !is_string($local) &&
                !is_array($local)
            ) {
                throw new \InvalidArgumentException(
                    'JOIN local key must be a string or array.'
                );
            }

            if (
                !is_string($foreign) &&
                !is_array($foreign)
            ) {
                throw new \InvalidArgumentException(
                    'JOIN foreign key must be a string or array.'
                );
            }

            if (
                is_array($local) !==
                is_array($foreign)
            ) {
                throw new \InvalidArgumentException(
                    'JOIN local and foreign keys must both be arrays '
                        . 'or both be scalar values.'
                );
            }

            $local = is_array($local)
                ? $local
                : [$local];

            $foreign = is_array($foreign)
                ? $foreign
                : [$foreign];

            if (
                $local === [] ||
                $foreign === []
            ) {
                throw new \InvalidArgumentException(
                    'JOIN keys cannot be empty.'
                );
            }

            if (
                count($local) !==
                count($foreign)
            ) {
                throw new \InvalidArgumentException(
                    'JOIN local and foreign keys must contain '
                        . 'the same number of columns.'
                );
            }

            $conditions = [];

            foreach ($local as $index => $localColumn) {
                if (!is_string($localColumn)) {
                    throw new \InvalidArgumentException(
                        'JOIN local columns must be strings.'
                    );
                }

                $foreignColumn = $foreign[$index] ?? null;

                if (!is_string($foreignColumn)) {
                    throw new \InvalidArgumentException(
                        'JOIN foreign columns must be strings.'
                    );
                }

                static::validateColumn(
                    $localColumn
                );

                static::validateColumn(
                    $foreignColumn
                );

                $conditions[] =
                    static::table()
                    . '.' . $localColumn
                    . ' = '
                    . $model::table()
                    . '.' . $foreignColumn;
            }

            $sql .= sprintf(
                ' %s JOIN %s ON %s',
                $type,
                $model::table(),
                implode(
                    ' AND ',
                    $conditions
                )
            );
        }

        return [
            'sql' => $sql,
            'params' => [],
        ];
    }

    /*
     * =========================================================================
     * PRIMARY KEY
     * =========================================================================
     */

    /**
     * Get and validate the primary-key definition.
     *
     * static::primary_key is resolved using late static binding.
     */
    protected static function primaryKeyDefinition(): string|array
    {
        $primaryKey = static::primary_key;

        if (is_string($primaryKey)) {
            if ($primaryKey === '') {
                throw new \LogicException(
                    static::class
                        . ' has an empty primary key.'
                );
            }

            static::validateIdentifier(
                $primaryKey
            );

            return $primaryKey;
        }

        if (
            !is_array($primaryKey) ||
            $primaryKey === []
        ) {
            throw new \LogicException(
                static::class
                    . ' must define primary_key as a string '
                    . 'or non-empty array.'
            );
        }

        foreach ($primaryKey as $column) {
            if (!is_string($column)) {
                throw new \LogicException(
                    static::class
                        . ' primary_key array must contain '
                        . 'only strings.'
                );
            }

            static::validateIdentifier(
                $column
            );
        }

        return $primaryKey;
    }

    protected static function buildPkWhere(
        array $values
    ): array {
        if ($values === []) {
            throw new \InvalidArgumentException(
                'Primary key values cannot be empty.'
            );
        }

        $conditions = [];
        $params = [];

        foreach ($values as $column => $value) {
            if (!is_string($column)) {
                throw new \InvalidArgumentException(
                    'Primary key column names must be strings.'
                );
            }

            static::validateColumn(
                $column
            );

            $conditions[] =
                "{$column} = ?";

            $params[] = $value;
        }

        return [
            'sql' => ' WHERE ' . implode(
                ' AND ',
                $conditions
            ),
            'params' => $params,
        ];
    }

    protected static function getPkParams(
        array $values
    ): array {
        return array_values($values);
    }

    /*
     * =========================================================================
     * VALIDATION
     * =========================================================================
     */

    protected static function validateColumn(
        mixed $column
    ): void {
        if (!is_string($column)) {
            throw new \InvalidArgumentException(
                'Column name must be a string.'
            );
        }

        if (!static::isValidIdentifier($column)) {
            throw new \InvalidArgumentException(
                "Invalid column name '{$column}'."
            );
        }
    }

    protected static function validateIdentifier(
        mixed $identifier
    ): void {
        if (!is_string($identifier)) {
            throw new \InvalidArgumentException(
                'Identifier must be a string.'
            );
        }

        if (!static::isValidIdentifier($identifier)) {
            throw new \InvalidArgumentException(
                "Invalid identifier '{$identifier}'."
            );
        }
    }

    protected static function isValidIdentifier(
        string $identifier
    ): bool {
        return preg_match(
            '/^[A-Za-z_][A-Za-z0-9_]*$/',
            $identifier
        ) === 1;
    }
}
