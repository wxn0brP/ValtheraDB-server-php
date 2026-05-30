<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/search.php';
require_once __DIR__ . '/updater.php';
require_once __DIR__ . '/id.php';
require_once __DIR__ . '/findOpts.php';

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

    if ($idGen && !isset($data['_id']))
        $data['_id'] = genId();

    $dbConfig = getDbConfig($dbName);
    db_init($dbConfig);
    global $_DB_DRIVER;

    $keys = array_keys($data);
    $columns = implode(
        ', ',
        array_map(fn($k) =>
            escapeIdentifier($k, $_DB_DRIVER), $keys)
    );
    $placeholders = implode(', ', array_fill(0, count($keys), '?'));

    $sql = "INSERT INTO " . escapeIdentifier($collection, $_DB_DRIVER) . " ({$columns}) VALUES ({$placeholders})";

    db_execute($sql, array_values($data));
    header('X-SQL-Query: ' . convertSqlAndParamsToString($sql, array_values($data)));

    db_close();
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

    $hasAggregation = $groupBy !== null || $count !== null || $min !== null || $max !== null || $avg !== null;
    $isRandomSort = $sortBy === 'random()';
    $needsPhpReverse = $reverse && $sortBy === null && !$hasAggregation;

    $dbConfig = getDbConfig($dbName);
    db_init($dbConfig);
    global $_DB_DRIVER;

    // reverse without sortBy
    if ($needsPhpReverse) {
        $sql = "SELECT * FROM " . escapeIdentifier($collection, $_DB_DRIVER);
        $whereParams = [];
        $whereClause = buildWhere($search, $whereParams);
        if ($whereClause)
            $sql .= " WHERE " . $whereClause;

        $results = db_fetch_all($sql, $whereParams);
        header('X-SQL-Query: ' . convertSqlAndParamsToString($sql, $whereParams));
        db_close();

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

    // Build SELECT clause
    if ($hasAggregation) {
        $selectParts = [];

        if ($groupBy !== null) {
            $groupByFields = is_array($groupBy) ? $groupBy : [$groupBy];
            foreach ($groupByFields as $f) {
                $selectParts[] = escapeIdentifier($f, $_DB_DRIVER);
            }
        }

        if ($count)
            foreach ($count as $outKey => $srcField)
                $selectParts[] = "COUNT(" . escapeIdentifier($srcField, $_DB_DRIVER) . ") AS " . escapeIdentifier($outKey, $_DB_DRIVER);

        if ($min)
            foreach ($min as $outKey => $srcField)
                $selectParts[] = "MIN(" . escapeIdentifier($srcField, $_DB_DRIVER) . ") AS " . escapeIdentifier($outKey, $_DB_DRIVER);

        if ($max)
            foreach ($max as $outKey => $srcField)
                $selectParts[] = "MAX(" . escapeIdentifier($srcField, $_DB_DRIVER) . ") AS " . escapeIdentifier($outKey, $_DB_DRIVER);

        if ($avg)
            foreach ($avg as $outKey => $srcField)
                $selectParts[] = "AVG(" . escapeIdentifier($srcField, $_DB_DRIVER) . ") AS " . escapeIdentifier($outKey, $_DB_DRIVER);

        $sql = "SELECT " . implode(', ', $selectParts) . " FROM " . escapeIdentifier($collection, $_DB_DRIVER);
    } else {
        $sql = "SELECT * FROM " . escapeIdentifier($collection, $_DB_DRIVER);
    }

    // WHERE clause
    $whereParams = [];
    $whereClause = buildWhere($search, $whereParams);
    if ($whereClause)
        $sql .= " WHERE " . $whereClause;

    // GROUP BY (aggregation)
    if ($hasAggregation && $groupBy !== null) {
        $groupByFields = is_array($groupBy) ? $groupBy : [$groupBy];
        $gbParts = array_map(fn($f) => escapeIdentifier($f, $_DB_DRIVER), $groupByFields);
        $sql .= " GROUP BY " . implode(', ', $gbParts);
    }

    // ORDER BY
    if ($isRandomSort) {
        $sql .= " ORDER BY RAND()";
    } elseif ($sortBy !== null) {
        $effectiveSortAsc = $sortAsc ?? true;
        if ($reverse)
            $effectiveSortAsc = !$effectiveSortAsc;
        $dir = $effectiveSortAsc ? 'ASC' : 'DESC';
        $sql .= " ORDER BY " . escapeIdentifier($sortBy, $_DB_DRIVER) . " {$dir}";
    }

    // LIMIT / OFFSET
    if ($limit !== null) {
        $sql .= " LIMIT " . intval($limit);
        if ($offset !== null)
            $sql .= " OFFSET " . intval($offset);
    }

    $results = db_fetch_all($sql, $whereParams);
    header('X-SQL-Query: ' . convertSqlAndParamsToString($sql, $whereParams));
    db_close();

    // Aggregation without groupBy: unwrap single-object array
    if ($hasAggregation && $groupBy === null)
        return !empty($results) ? $results[0] : [];

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
    global $_DB_DRIVER;

    $selectSql = "SELECT * FROM " . escapeIdentifier($collection, $_DB_DRIVER);
    $whereParams = [];
    $whereClause = buildWhere($search, $whereParams);

    if ($whereClause)
        $selectSql .= " WHERE " . $whereClause;

    if ($one)
        $selectSql .= " LIMIT 1";

    $matchingDocs = db_fetch_all($selectSql, $whereParams);

    if (empty($matchingDocs))
        return [];

    $results = [];

    foreach ($matchingDocs as $doc) {
        $newData = applyUpdater($doc, $updater);

        $newData['_id'] = $doc['_id'];

        $keys = array_keys($newData);
        $keyIdIndex = array_search('_id', $keys);
        if ($keyIdIndex !== false) {
            unset($keys[$keyIdIndex]);
        }

        $setClause = implode(', ', array_map(fn($k) => escapeIdentifier($k, $_DB_DRIVER) . " = ?", $keys));
        $updateValues = array_values(array_filter($newData, fn($k) => $k !== '_id', ARRAY_FILTER_USE_KEY));

        $updateSql = "UPDATE " . escapeIdentifier($collection, $_DB_DRIVER) . " SET {$setClause} WHERE " . escapeIdentifier('_id', $_DB_DRIVER) . " = ?";
        $updateParams = [...$updateValues, $doc['_id']];

        db_execute($updateSql, $updateParams);

        $updatedDoc = db_fetch_one(
            "SELECT * FROM " . escapeIdentifier($collection, $_DB_DRIVER) . " WHERE " . escapeIdentifier('_id', $_DB_DRIVER) . " = ?",
            [$doc['_id']],
        );

        $results[] = $updatedDoc;
    }

    db_close();
    header('X-SQL-Query: ' . convertSqlAndParamsToString($updateSql, $updateParams));
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
    global $_DB_DRIVER;

    $selectSql = "SELECT * FROM " . escapeIdentifier($collection, $_DB_DRIVER);
    $whereParams = [];
    $whereClause = buildWhere($search, $whereParams);

    if ($whereClause)
        $selectSql .= " WHERE " . $whereClause;

    if ($one)
        $selectSql .= " LIMIT 1";

    $matchingDocs = db_fetch_all($selectSql, $whereParams);

    if (empty($matchingDocs))
        return [];

    $deleteSql = '';
    $deletedDocs = [];

    foreach ($matchingDocs as $doc) {
        $deleteSql = "DELETE FROM " . escapeIdentifier($collection, $_DB_DRIVER) . " WHERE " . escapeIdentifier('_id', $_DB_DRIVER) . " = ?";
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
