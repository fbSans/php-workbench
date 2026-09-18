<?php

declare(strict_types=1);

namespace Base;

abstract class Model
{
    protected const table = null;
    protected const primary_key = 'id';

    protected array $fillable = [];
    protected array $hidden = [];
    protected array $attributes = [];
    protected array $primaryKeyValues = [];

    protected static ?\PDO $db = null;

    /*
     * ---------------------------------------------------------
     * Connection
     * ---------------------------------------------------------
     */

    public static function setConnection(\PDO $pdo): void
    {
        static::$db = $pdo;
    }

    public static function closeConnection(): void
    {
        static::$db = null;
    }

    protected static function connection(): \PDO
    {
        if (static::$db === null) {
            throw new \RuntimeException(
                'Database connection has not been initialized.'
            );
        }

        return static::$db;
    }

    /*
     * ---------------------------------------------------------
     * Construction / attributes
     * ---------------------------------------------------------
     */

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->fillable, true)) {
                $this->__set($key, $value);
            }
        }

        $pk = static::primary_key;

        if (is_array($pk)) {
            foreach ($pk as $column) {
                $value = $data[$column] ?? null;

                $this->primaryKeyValues[$column] = $value;

                if (array_key_exists($column, $data)) {
                    $this->attributes[$column] = $value;
                }
            }
        } else {
            $value = $data[$pk] ?? null;

            $this->primaryKeyValues[$pk] = $value;

            if (array_key_exists($pk, $data)) {
                $this->attributes[$pk] = $value;
            }
        }
    }

    public function __set(string $name, mixed $value): void
    {
        if (!in_array($name, $this->fillable, true)) {
            throw new \Exception(
                "Cannot set non-fillable attribute '$name'."
            );
        }

        $this->attributes[$name] = $value;
    }

    public function __get(string $name): mixed
    {
        if (in_array($name, $this->hidden, true)) {
            throw new \Exception(
                "Attribute '$name' is hidden."
            );
        }

        return $this->attributes[$name] ?? null;
    }

    protected function getRaw(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function toArray(): array
    {
        return array_diff_key(
            $this->attributes,
            array_flip($this->hidden)
        );
    }

    /*
     * ---------------------------------------------------------
     * Table / columns
     * ---------------------------------------------------------
     */

    public static function table(): string
    {
        $table = static::table;

        if (!is_string($table)) {
            throw new \Exception(
                static::class . ' must define table constant.'
            );
        }

        return $table;
    }

    protected static function validateColumn(string $column): string
    {
        if (!preg_match(
            '/^[a-zA-Z_][a-zA-Z0-9_]*$/',
            $column
        )) {
            throw new \InvalidArgumentException(
                "Invalid column name: {$column}"
            );
        }

        return $column;
    }

    /*
     * ---------------------------------------------------------
     * Hydration
     * ---------------------------------------------------------
     */

    protected static function hydrate(array $row): static
    {
        $model = new static();

        $model->attributes = $row;

        $pk = static::primary_key;

        if (is_array($pk)) {
            foreach ($pk as $column) {
                $model->primaryKeyValues[$column] =
                    $row[$column] ?? null;
            }
        } else {
            $model->primaryKeyValues[$pk] =
                $row[$pk] ?? null;
        }

        return $model;
    }

    /*
     * ---------------------------------------------------------
     * WHERE
     * ---------------------------------------------------------
     *
     * Supported forms:
     *
     *     'name' => 'John'
     *
     *     'age' => ['>', 18]
     *
     *     'name' => ['LIKE', 'John%']
     *
     *     'id' => ['IN', [1, 2, 3]]
     *
     *     'id' => ['NOT IN', [1, 2, 3]]
     *
     *     'deleted_at' => null
     *
     *     'deleted_at' => ['IS NULL', null]
     *
     *     'deleted_at' => ['IS NOT NULL', null]
     *
     * All values are parameterized.
     */

    protected static function buildWhere(array $conditions): array
    {
        if ($conditions === []) {
            return [
                'sql' => '1',
                'params' => [],
            ];
        }

        $parts = [];
        $params = [];

        $allowedOperators = [
            '=',
            '!=',
            '<>',
            '<',
            '<=',
            '>',
            '>=',
            'LIKE',
            'NOT LIKE',
            'IN',
            'NOT IN',
            'IS NULL',
            'IS NOT NULL',
        ];

        foreach ($conditions as $column => $condition) {
            $column = static::validateColumn($column);

            /*
             * -------------------------------------------------
             * Simple equality
             *
             *     'owner_id' => 10
             *
             * becomes:
             *
             *     owner_id = :where_0
             * -------------------------------------------------
             */

            if (!is_array($condition)) {
                if ($condition === null) {
                    $parts[] = "$column IS NULL";
                    continue;
                }

                $placeholder = 'where_' . count($params);

                $parts[] =
                    "$column = :$placeholder";

                $params[$placeholder] = $condition;

                continue;
            }

            /*
             * -------------------------------------------------
             * Operator form
             *
             *     'size' => ['>', 1000]
             * -------------------------------------------------
             */

            if (count($condition) !== 2) {
                throw new \InvalidArgumentException(
                    "WHERE condition for '$column' " .
                        "must contain operator and value."
                );
            }

            [$operator, $value] = $condition;

            if (!is_string($operator)) {
                throw new \InvalidArgumentException(
                    "WHERE operator for '$column' must be a string."
                );
            }

            $operator = strtoupper(trim($operator));

            if (!in_array($operator, $allowedOperators, true)) {
                throw new \InvalidArgumentException(
                    "Invalid WHERE operator: {$operator}"
                );
            }

            /*
             * -------------------------------------------------
             * IS NULL / IS NOT NULL
             *
             * No parameter is required.
             * -------------------------------------------------
             */

            if (
                $operator === 'IS NULL' ||
                $operator === 'IS NOT NULL'
            ) {
                $parts[] = "$column $operator";

                continue;
            }

            /*
             * -------------------------------------------------
             * IN / NOT IN
             *
             * Each value receives its own placeholder.
             * -------------------------------------------------
             */

            if (
                $operator === 'IN' ||
                $operator === 'NOT IN'
            ) {
                if (!is_array($value)) {
                    throw new \InvalidArgumentException(
                        "$operator requires an array of values."
                    );
                }

                /*
                 * Empty IN lists cannot be represented as:
                 *
                 *     IN ()
                 *
                 * Instead:
                 *
                 *     x IN []      -> false
                 *     x NOT IN []  -> true
                 */

                if ($value === []) {
                    $parts[] =
                        $operator === 'IN'
                        ? '0'
                        : '1';

                    continue;
                }

                $placeholders = [];

                foreach ($value as $item) {
                    if ($item === null) {
                        throw new \InvalidArgumentException(
                            "$operator cannot contain NULL. " .
                                "Use IS NULL separately."
                        );
                    }

                    $placeholder =
                        'where_' . count($params);

                    $placeholders[] =
                        ':' . $placeholder;

                    $params[$placeholder] = $item;
                }

                $parts[] =
                    "$column $operator (" .
                    implode(', ', $placeholders) .
                    ')';

                continue;
            }

            /*
             * -------------------------------------------------
             * NULL with normal operators
             * -------------------------------------------------
             *
             *     ['=', null]
             *         -> IS NULL
             *
             *     ['!=', null]
             *         -> IS NOT NULL
             */

            if ($value === null) {
                if ($operator === '=') {
                    $parts[] = "$column IS NULL";

                    continue;
                }

                if (
                    $operator === '!=' ||
                    $operator === '<>'
                ) {
                    $parts[] = "$column IS NOT NULL";

                    continue;
                }

                throw new \InvalidArgumentException(
                    "NULL cannot be used with " .
                        "WHERE operator {$operator}."
                );
            }

            /*
             * -------------------------------------------------
             * Normal operator
             *
             *     ['>', 100]
             *     ['LIKE', 'John%']
             * -------------------------------------------------
             */

            $placeholder =
                'where_' . count($params);

            $parts[] =
                "$column $operator :$placeholder";

            $params[$placeholder] = $value;
        }

        return [
            'sql' => implode(' AND ', $parts),
            'params' => $params,
        ];
    }

    /*
     * ---------------------------------------------------------
     * SELECT
     * ---------------------------------------------------------
     */

    protected static function buildSelect(
        array|string|null $select
    ): string {
        if ($select === null) {
            return '*';
        }

        if (is_string($select)) {
            return $select;
        }

        if ($select === []) {
            return '*';
        }

        return implode(', ', $select);
    }

    /*
     * ---------------------------------------------------------
     * AGGREGATE DETECTION
     * ---------------------------------------------------------
     */

    protected static function isAggregateSelect(
        array|string|null $select
    ): bool {
        if ($select === null) {
            return false;
        }

        $expressions = is_array($select)
            ? $select
            : [$select];

        foreach ($expressions as $expression) {
            if (preg_match(
                '/\b(COUNT|SUM|AVG|MIN|MAX)\s*\(/i',
                $expression
            )) {
                return true;
            }
        }

        return false;
    }

    /*
     * ---------------------------------------------------------
     * GROUP BY
     * ---------------------------------------------------------
     */

    protected static function buildGroupBy(
        array|string|null $groupBy
    ): string {
        if ($groupBy === null) {
            return '';
        }

        if (is_string($groupBy)) {
            $groupBy = [$groupBy];
        }

        if ($groupBy === []) {
            return '';
        }

        $columns = array_map(
            fn(string $column): string =>
            static::validateColumn($column),
            $groupBy
        );

        return ' GROUP BY ' . implode(', ', $columns);
    }

    /*
     * ---------------------------------------------------------
     * HAVING
     * ---------------------------------------------------------
     */

    protected static function buildHaving(array $conditions): array
    {
        if ($conditions === []) {
            return [
                'sql' => '',
                'params' => [],
            ];
        }

        $parts = [];
        $params = [];

        foreach ($conditions as $condition) {
            if (!is_array($condition) || count($condition) !== 3) {
                throw new \InvalidArgumentException(
                    'HAVING conditions must contain expression, operator and value.'
                );
            }

            [$expression, $operator, $value] = $condition;

            if (!is_string($expression)) {
                throw new \InvalidArgumentException(
                    'HAVING expression must be a string.'
                );
            }

            $operator = strtoupper((string) $operator);

            $allowedOperators = [
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

            if (!in_array($operator, $allowedOperators, true)) {
                throw new \InvalidArgumentException(
                    "Invalid HAVING operator: {$operator}"
                );
            }

            $placeholder =
                'having_' . count($params);

            $parts[] =
                $expression .
                ' ' .
                $operator .
                ' :' .
                $placeholder;

            $params[$placeholder] = $value;
        }

        return [
            'sql' => ' HAVING ' . implode(' AND ', $parts),
            'params' => $params,
        ];
    }

    /*
     * ---------------------------------------------------------
     * ORDER BY
     * ---------------------------------------------------------
     */

    protected static function buildOrderBy(
        array|string|null $orderBy,
        bool $aggregate = false
    ): string {
        if ($orderBy === null) {
            /*
             * Aggregate queries cannot safely default to
             * ordering by the primary key because it might
             * not be present in SELECT.
             */

            if ($aggregate) {
                return '';
            }

            $pk = static::primary_key;

            $orderBy = is_array($pk)
                ? $pk[0]
                : $pk;
        }

        if (is_string($orderBy)) {
            $orderBy = [
                $orderBy => 'ASC',
            ];
        }

        if ($orderBy === []) {
            return '';
        }

        $parts = [];

        foreach ($orderBy as $column => $direction) {
            $column =
                static::validateColumn((string) $column);

            $direction =
                strtoupper((string) $direction);

            if (!in_array(
                $direction,
                ['ASC', 'DESC'],
                true
            )) {
                throw new \InvalidArgumentException(
                    'Sort direction must be ASC or DESC.'
                );
            }

            $parts[] =
                $column . ' ' . $direction;
        }

        return ' ORDER BY ' .
            implode(', ', $parts);
    }

    /*
     * ---------------------------------------------------------
     * FETCH PAGE
     * ---------------------------------------------------------
     */

    public static function fetchPage(
        int &$page,
        int $pageSize,
        array $wheres = [],
        array|string|null $select = null,
        array|string|null $groupBy = null,
        array $having = [],
        array|string|null $orderBy = null
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException(
                'Page size must be greater than zero.'
            );
        }

        if ($page < 1) {
            $page = 1;
        }

        $where = static::buildWhere($wheres);
        $havingData = static::buildHaving($having);

        $selectSql =
            static::buildSelect($select);

        $groupBySql =
            static::buildGroupBy($groupBy);

        /*
         * Determine whether the result represents entities
         * or aggregate/grouped data.
         */

        $aggregate =
            $groupBySql !== '' ||
            $havingData['sql'] !== '' ||
            static::isAggregateSelect($select);

        $orderBySql =
            static::buildOrderBy(
                $orderBy,
                $aggregate
            );

        $db = static::connection();
        $table = static::table();

        /*
         * -----------------------------------------------------
         * Base query
         * -----------------------------------------------------
         */

        $baseSql =
            'SELECT ' .
            $selectSql .
            ' FROM ' .
            $table .
            ' WHERE ' .
            $where['sql'] .
            $groupBySql .
            $havingData['sql'];

        $baseParams = array_merge(
            $where['params'],
            $havingData['params']
        );

        /*
         * -----------------------------------------------------
         * Count
         * -----------------------------------------------------
         *
         * Grouped queries must count groups.
         *
         * Aggregate queries without GROUP BY produce one
         * result row, so they are also treated as aggregate.
         */

        if ($aggregate) {
            $countSql =
                'SELECT COUNT(*) FROM (' .
                $baseSql .
                ') AS count_query';

            $countParams = $baseParams;
        } else {
            $countSql =
                'SELECT COUNT(*) FROM ' .
                $table .
                ' WHERE ' .
                $where['sql'];

            $countParams =
                $where['params'];
        }

        $countStmt =
            $db->prepare($countSql);

        $countStmt->execute(
            $countParams
        );

        $totalItems =
            (int) $countStmt->fetchColumn();

        $totalPages =
            $totalItems === 0
            ? 0
            : (int) ceil(
                $totalItems / $pageSize
            );

        /*
         * Move an out-of-range page to the last page.
         */

        if (
            $totalPages > 0 &&
            $page > $totalPages
        ) {
            $page = $totalPages;
        }

        /*
         * No results.
         */

        if ($totalItems === 0) {
            return [
                'items' => [],
                'page' => $page,
                'pageSize' => $pageSize,
                'totalItems' => 0,
                'totalPages' => 0,
            ];
        }

        $offset =
            ($page - 1) * $pageSize;

        /*
         * -----------------------------------------------------
         * Final query
         * -----------------------------------------------------
         */

        $sql =
            $baseSql .
            $orderBySql .
            ' LIMIT :limit OFFSET :offset';

        $stmt =
            $db->prepare($sql);

        foreach (
            $baseParams as $name => $value
        ) {
            $stmt->bindValue(
                ':' . $name,
                $value
            );
        }

        $stmt->bindValue(
            ':limit',
            $pageSize,
            \PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':offset',
            $offset,
            \PDO::PARAM_INT
        );

        $stmt->execute();

        $rows =
            $stmt->fetchAll(
                \PDO::FETCH_ASSOC
            );

        /*
         * Aggregate queries return arrays.
         *
         * Normal queries return hydrated models.
         */

        if ($aggregate) {
            $items = $rows;
        } else {
            $items = array_map(
                fn(array $row): static =>
                static::hydrate($row),
                $rows
            );
        }

        return [
            'items' => $items,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
        ];
    }

    /*
     * ---------------------------------------------------------
     * FIND
     * ---------------------------------------------------------
     */

    public static function find(
        mixed $values
    ): ?static {
        $pk = static::primary_key;
        $conditions = [];

        if (is_array($pk)) {
            if (
                !is_array($values) ||
                count($pk) !== count($values)
            ) {
                throw new \InvalidArgumentException(
                    'Number of values does not match primary key columns.'
                );
            }

            foreach (
                $pk as $index => $column
            ) {
                $conditions[$column] =
                    $values[$index];
            }
        } else {
            $conditions[$pk] = $values;
        }

        $page = 1;

        $result = static::fetchPage(
            page: $page,
            pageSize: 1,
            wheres: $conditions
        );

        return $result['items'][0] ?? null;
    }

    /*
     * ---------------------------------------------------------
     * PRIMARY KEY
     * ---------------------------------------------------------
     */

    protected function buildPkWhere(): string
    {
        $pk = static::primary_key;

        if (is_array($pk)) {
            return implode(
                ' AND ',
                array_map(
                    fn($column) =>
                    static::validateColumn(
                        $column
                    ) .
                        " = :$column",
                    $pk
                )
            );
        }

        $pk =
            static::validateColumn($pk);

        return "$pk = :$pk";
    }

    protected function getPkParams(): array
    {
        return $this->primaryKeyValues;
    }

    /*
     * ---------------------------------------------------------
     * CREATE
     * ---------------------------------------------------------
     */

    public function create(): bool
    {
        $pk = static::primary_key;

        $attributes =
            $this->attributes;

        if (!is_array($pk)) {
            if (
                !array_key_exists(
                    $pk,
                    $this->primaryKeyValues
                ) ||
                $this->primaryKeyValues[$pk] === null
            ) {
                unset($attributes[$pk]);
            }
        }

        if ($attributes === []) {
            throw new \Exception(
                'Cannot create model without attributes.'
            );
        }

        $columns =
            array_keys($attributes);

        $placeholders = array_map(
            fn($column) => ':' . $column,
            $columns
        );

        $sql =
            'INSERT INTO ' .
            static::table() .
            ' (' .
            implode(', ', $columns) .
            ') VALUES (' .
            implode(', ', $placeholders) .
            ')';

        $stmt =
            static::connection()->prepare($sql);

        $success =
            $stmt->execute($attributes);

        if (
            $success &&
            !is_array($pk)
        ) {
            $id =
                static::connection()->lastInsertId();

            if (
                $id !== '' &&
                $id !== '0'
            ) {
                $this->primaryKeyValues[$pk] =
                    $id;

                $this->attributes[$pk] =
                    $id;
            }
        }

        return $success;
    }

    public static function get(array $conditions = []): array
    {
        $where = static::buildWhere($conditions);

        $sql = 'SELECT * FROM ' . static::table();

        if ($where['sql'] !== '') {
            $sql .= ' WHERE ' . $where['sql'];
        }

        $stmt = static::connection()->prepare($sql);
        $stmt->execute($where['params']);

        return array_map(
            fn(array $row) => static::hydrate($row),
            $stmt->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    public static function createOne(
        array $attributes
    ): static {
        $model =
            new static($attributes);

        $model->create();

        return $model;
    }

    /*
     * ---------------------------------------------------------
     * UPDATE
     * ---------------------------------------------------------
     */

    public function update(): bool
    {
        $attributes =
            $this->attributes;

        $pk = static::primary_key;

        if (is_array($pk)) {
            foreach ($pk as $column) {
                unset($attributes[$column]);
            }
        } else {
            unset($attributes[$pk]);
        }

        if ($attributes === []) {
            return true;
        }

        $setStr = implode(
            ', ',
            array_map(
                fn($column) =>
                static::validateColumn(
                    $column
                ) .
                    " = :$column",
                array_keys($attributes)
            )
        );

        $sql =
            'UPDATE ' .
            static::table() .
            ' SET ' .
            $setStr .
            ' WHERE ' .
            $this->buildPkWhere();

        $params = array_merge(
            $attributes,
            $this->getPkParams()
        );

        $stmt =
            static::connection()->prepare($sql);

        return $stmt->execute($params);
    }

    /*
     * ---------------------------------------------------------
     * DELETE
     * ---------------------------------------------------------
     */

    public function delete(): bool
    {
        $sql =
            'DELETE FROM ' .
            static::table() .
            ' WHERE ' .
            $this->buildPkWhere();

        $stmt =
            static::connection()->prepare($sql);

        return $stmt->execute(
            $this->getPkParams()
        );
    }
}
