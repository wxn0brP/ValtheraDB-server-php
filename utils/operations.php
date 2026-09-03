<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/search.php';
require_once __DIR__ . '/updater.php';
require_once __DIR__ . '/id.php';
require_once __DIR__ . '/findOpts.php';

function createTableIfNotExists(string $collection): void
{
    $sql = 'CREATE TABLE IF NOT EXISTS ' . escapeIdentifier($collection) . ' (_id VARCHAR(64) PRIMARY KEY)';
    db_execute($sql, []);
}

function isColumnNotFoundError(Throwable $e): bool
{
    return str_contains($e->getMessage(), 'Unknown column')
        || str_contains($e->getMessage(), "doesn't exist");
}

function safeFetchAll(string $sql, array $params): array
{
    try {
        return db_fetch_all($sql, $params);
    } catch (Throwable $e) {
        if (isColumnNotFoundError($e)) {
            return [];
        }
        throw $e;
    }
}

function decodeDocFields(array $doc): array
{
    foreach ($doc as $key => $value) {
        if ($key === '_id') continue;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                $doc[$key] = $decoded;
            } elseif (is_numeric($value)) {
                $doc[$key] = $value + 0;
            }
        }
    }
    return $doc;
}

function encodeDocFields(array $doc): array
{
    foreach ($doc as $key => $value) {
        if ($key !== '_id' && (is_array($value) || is_object($value))) {
            $doc[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
    }
    return $doc;
}

function ensureColumns(string $collection, array $data): void
{
    $sql = 'SHOW COLUMNS FROM ' . escapeIdentifier($collection);
    $columns = db_fetch_all($sql, []);
    $existingColumns = array_column($columns, 'Field');

    foreach (array_keys($data) as $key) {
        if ($key === '_id') continue;
        if (!in_array($key, $existingColumns)) {
            $alterSql = 'ALTER TABLE ' . escapeIdentifier($collection)
                . ' ADD COLUMN ' . escapeIdentifier($key) . ' TEXT';
            db_execute($alterSql, []);
        }
    }
}

function add(array $params): array
{
    $collection = $params['collection'] ?? null;
    $data = $params['data'] ?? [];
    $idGen = $params['id_gen'] ?? true;
    $dbName = $params['db'] ?? null;

    if (!$collection)
        throw new Exception("Missing required parameter: collection");

    if (empty($data))
        throw new Exception("Missing required parameter: data");

    if (!is_array($data))
        $data = [$data];

    $hadId = isset($data['_id']);
    if (!$hadId)
        $data['_id'] = genId();

    $dbConfig = getDbConfig($dbName);
    db_init($dbConfig);

    createTableIfNotExists($collection);
    ensureColumns($collection, $data);

    $encodedData = encodeDocFields($data);
    $keys = array_keys($encodedData);
    $columns = implode(
        ', ',
        array_map(fn($k) =>
            escapeIdentifier($k), $keys)
    );
    $placeholders = implode(', ', array_fill(0, count($keys), '?'));

    $sql = "INSERT INTO " . escapeIdentifier($collection) . " ({$columns}) VALUES ({$placeholders})";

    db_execute($sql, array_values($encodedData));
    header('X-SQL-Query: ' . convertSqlAndParamsToString($sql, array_values($encodedData)));

    db_close();

    if (!$idGen && !$hadId) {
        unset($data['_id']);
    }

    return $data;
}

function find(array $params): array
{
    $collection = $params['collection'] ?? null;
    $search = $params['search'] ?? [];
    $dbName = $params['db'] ?? null;
    $dbFindOpts = $params['dbFindOpts'] ?? [];
    $findOpts = $params['findOpts'] ?? [];

    if (!$collection)
        throw new Exception("Missing required parameter: collection");

    $limit = $dbFindOpts['limit'] ?? null;
    $offset = $dbFindOpts['offset'] ?? null;
    $sortBy = $dbFindOpts['sortBy'] ?? null;
    $sortAsc = $dbFindOpts['sortAsc'] ?? null;
    $reverse = $dbFindOpts['reverse'] ?? false;
    $groupBy = $dbFindOpts['groupBy'] ?? null;
    $count = $dbFindOpts['count'] ?? null;
    $min = $dbFindOpts['min'] ?? null;
    $max = $dbFindOpts['max'] ?? null;
    $avg = $dbFindOpts['avg'] ?? null;
    $sum = $dbFindOpts['sum'] ?? null;
    $distinct = $dbFindOpts['distinct'] ?? null;

    if ($limit === INF || $limit === 'Infinity') $limit = null;

    $hasAggregation = $groupBy !== null || $count !== null || $min !== null || $max !== null || $avg !== null || $sum !== null;
    $isRandomSort = $sortBy === 'random()';
    $needsPhpReverse = $reverse && $sortBy === null && !$hasAggregation;
    $isMultiSort = is_array($sortBy);

    $dbConfig = getDbConfig($dbName);
    db_init($dbConfig);

    if ($needsPhpReverse) {
        $sql = "SELECT * FROM " . escapeIdentifier($collection);
        $whereParams = [];
        $whereClause = buildWhere($search, $whereParams);
        if ($whereClause)
            $sql .= " WHERE " . $whereClause;

        $results = safeFetchAll($sql, $whereParams);
        header('X-SQL-Query: ' . convertSqlAndParamsToString($sql, $whereParams));
        db_close();

        $results = array_map(fn($row) => decodeDocFields($row), $results);
        $results = array_reverse($results);

        $effectiveOffset = $offset !== null ? (int) $offset : 0;
        if ($limit !== null && $limit !== -1)
            $results = array_slice($results, $effectiveOffset, (int) $limit);
        elseif ($effectiveOffset > 0)
            $results = array_slice($results, $effectiveOffset);

        if (!empty($findOpts))
            $results = array_map(fn($row) => applyFindOpts($row, $findOpts), $results);

        return $results;
    }

    if ($hasAggregation) {
        $selectParts = [];

        if ($groupBy !== null) {
            $groupByFields = is_array($groupBy) ? $groupBy : [$groupBy];
            foreach ($groupByFields as $f) {
                $selectParts[] = escapeIdentifier($f);
            }
        }

        if ($count)
            foreach ($count as $outKey => $srcField)
                $selectParts[] = "COUNT(" . escapeIdentifier($srcField) . ") AS " . escapeIdentifier($outKey);

        if ($min)
            foreach ($min as $outKey => $srcField)
                $selectParts[] = "MIN(" . escapeIdentifier($srcField) . ") AS " . escapeIdentifier($outKey);

        if ($max)
            foreach ($max as $outKey => $srcField)
                $selectParts[] = "MAX(" . escapeIdentifier($srcField) . ") AS " . escapeIdentifier($outKey);

        if ($avg)
            foreach ($avg as $outKey => $srcField)
                $selectParts[] = "AVG(" . escapeIdentifier($srcField) . ") AS " . escapeIdentifier($outKey);

        if ($sum)
            foreach ($sum as $outKey => $srcField)
                $selectParts[] = "SUM(" . escapeIdentifier($srcField) . ") AS " . escapeIdentifier($outKey);

        $sql = "SELECT " . implode(', ', $selectParts) . " FROM " . escapeIdentifier($collection);
    } else {
        $sql = "SELECT * FROM " . escapeIdentifier($collection);
    }

    $whereParams = [];
    $whereClause = buildWhere($search, $whereParams);
    if ($whereClause)
        $sql .= " WHERE " . $whereClause;

    if ($hasAggregation && $groupBy !== null) {
        $groupByFields = is_array($groupBy) ? $groupBy : [$groupBy];
        $gbParts = array_map(fn($f) => escapeIdentifier($f), $groupByFields);
        $sql .= " GROUP BY " . implode(', ', $gbParts);
    }

    if ($isRandomSort) {
        $sql .= " ORDER BY RAND()";
    } elseif ($isMultiSort) {
        $orderParts = [];
        foreach ($sortBy as $sortItem) {
            $field = is_array($sortItem) ? ($sortItem['field'] ?? '') : $sortItem;
            $asc = is_array($sortItem) ? ($sortItem['asc'] ?? true) : ($sortAsc ?? true);
            if ($reverse) $asc = !$asc;
            $dir = $asc ? 'ASC' : 'DESC';
            $orderParts[] = escapeIdentifier($field) . " {$dir}";
        }
        $sql .= " ORDER BY " . implode(', ', $orderParts);
    } elseif ($sortBy !== null) {
        $effectiveSortAsc = $sortAsc ?? true;
        if ($reverse)
            $effectiveSortAsc = !$effectiveSortAsc;
        $dir = $effectiveSortAsc ? 'ASC' : 'DESC';
        $sql .= " ORDER BY " . escapeIdentifier($sortBy) . " {$dir}";
    }

    if ($limit !== null && $limit !== -1) {
        $sql .= " LIMIT " . intval($limit);
        if ($offset !== null)
            $sql .= " OFFSET " . intval($offset);
    } elseif ($offset !== null && $offset > 0) {
        $sql .= " LIMIT 18446744073709551615 OFFSET " . intval($offset);
    }

    $results = safeFetchAll($sql, $whereParams);
    header('X-SQL-Query: ' . convertSqlAndParamsToString($sql, $whereParams));
    db_close();

    $results = array_map(fn($row) => decodeDocFields($row), $results);

    if ($hasAggregation && $groupBy === null)
        return !empty($results) ? $results[0] : [];

    if ($distinct !== null) {
        $seen = [];
        $deduped = [];
        foreach ($results as $row) {
            $val = $row[$distinct] ?? null;
            $key = is_array($val) ? json_encode($val) : (string) $val;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $deduped[] = $row;
            }
        }
        $results = $deduped;
    }

    if (!empty($findOpts) && !$hasAggregation)
        $results = array_map(fn($row) => applyFindOpts($row, $findOpts), $results);

    return $results;
}

function update(array $params, bool $one = false): array
{
    $collection = $params['collection'] ?? null;
    $search = $params['search'] ?? [];
    $updater = $params['updater'] ?? [];
    $dbName = $params['db'] ?? null;

    if (!$collection)
        throw new Exception("Missing required parameter: collection");

    if (empty($updater))
        throw new Exception("Missing required parameter: updater");

    $dbConfig = getDbConfig($dbName);
    db_init($dbConfig);

    $selectSql = "SELECT * FROM " . escapeIdentifier($collection);
    $whereParams = [];
    $whereClause = buildWhere($search, $whereParams);

    if ($whereClause)
        $selectSql .= " WHERE " . $whereClause;

    if ($one)
        $selectSql .= " LIMIT 1";

    $matchingDocs = safeFetchAll($selectSql, $whereParams);

    if (empty($matchingDocs))
        return [];

    $results = [];

    foreach ($matchingDocs as $doc) {
        $doc = decodeDocFields($doc);
        [$newData, $removedKeys] = applyUpdater($doc, $updater);

        $newData['_id'] = $doc['_id'];

        foreach ($removedKeys as $key => $_) {
            if (array_key_exists($key, $newData)) {
                $newData[$key] = null;
            }
        }

        ensureColumns($collection, $newData);

        $keys = array_keys($newData);
        $keyIdIndex = array_search('_id', $keys);
        if ($keyIdIndex !== false) {
            unset($keys[$keyIdIndex]);
        }

        $encodedData = encodeDocFields($newData);

        if (empty($keys)) {
            $updatedDoc = db_fetch_one(
                "SELECT * FROM " . escapeIdentifier($collection) . " WHERE " . escapeIdentifier('_id') . " = ?",
                [$doc['_id']],
            );
            $updatedDoc = decodeDocFields($updatedDoc);
            foreach (array_keys($removedKeys) as $key) {
                unset($updatedDoc[$key]);
            }
            $results[] = $updatedDoc;
            continue;
        }

        $setClause = implode(', ', array_map(fn($k) => escapeIdentifier($k) . " = ?", $keys));
        $updateValues = array_values(array_filter($encodedData, fn($k) => $k !== '_id', ARRAY_FILTER_USE_KEY));

        $updateSql = "UPDATE " . escapeIdentifier($collection) . " SET {$setClause} WHERE " . escapeIdentifier('_id') . " = ?";
        $updateParams = [...$updateValues, $doc['_id']];

        db_execute($updateSql, $updateParams);

        $updatedDoc = db_fetch_one(
            "SELECT * FROM " . escapeIdentifier($collection) . " WHERE " . escapeIdentifier('_id') . " = ?",
            [$doc['_id']],
        );

        $updatedDoc = decodeDocFields($updatedDoc);
        foreach (array_keys($removedKeys) as $key) {
            unset($updatedDoc[$key]);
        }
        $results[] = $updatedDoc;
    }

    db_close();
    if (!empty($updateSql)) {
        header('X-SQL-Query: ' . convertSqlAndParamsToString($updateSql, $updateParams));
    }
    return $results;
}

function updateOne(array $params): ?array
{
    $results = update($params, true);
    return !empty($results) ? $results[0] : null;
}

function remove(array $params, bool $one = false): array
{
    $collection = $params['collection'] ?? null;
    $search = $params['search'] ?? [];
    $dbName = $params['db'] ?? null;

    if (!$collection)
        throw new Exception("Missing required parameter: collection");

    $dbConfig = getDbConfig($dbName);
    db_init($dbConfig);

    $selectSql = "SELECT * FROM " . escapeIdentifier($collection);
    $whereParams = [];
    $whereClause = buildWhere($search, $whereParams);

    if ($whereClause)
        $selectSql .= " WHERE " . $whereClause;

    if ($one)
        $selectSql .= " LIMIT 1";

    $matchingDocs = safeFetchAll($selectSql, $whereParams);
    $matchingDocs = array_map(fn($row) => decodeDocFields($row), $matchingDocs);

    if (empty($matchingDocs))
        return [];

    $deleteSql = '';
    $deletedDocs = [];

    foreach ($matchingDocs as $doc) {
        $deleteSql = "DELETE FROM " . escapeIdentifier($collection) . " WHERE " . escapeIdentifier('_id') . " = ?";
        db_execute($deleteSql, [$doc['_id']]);
        $deletedDocs[] = $doc;
    }

    db_close();
    header('X-SQL-Query: ' . convertSqlAndParamsToString($deleteSql, [$doc['_id'] ?? null]));
    return $deletedDocs;
}

function removeOne(array $params): ?array
{
    $results = remove($params, true);
    return !empty($results) ? $results[0] : null;
}
