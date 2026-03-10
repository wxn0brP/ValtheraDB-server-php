<?php
/**
 * Database operations - core business logic
 * Provides functions for CRUD operations on collections
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/search.php';

/**
 * Insert a new document into a collection
 *
 * @param array $params Operation parameters (collection, data, id_gen, db)
 * @return array Inserted document
 * @throws Exception If required parameters are missing
 */
function add(array $params): array {
    $collection = $params['collection'] ?? null;
    $data = $params['data'] ?? [];
    $idGen = $params['id_gen'] ?? true;
    $dbName = $params['db'] ?? null;

    if (!$collection) {
        throw new Exception("Missing required parameter: collection");
    }

    if (empty($data)) {
        throw new Exception("Missing required parameter: data");
    }

    if ($idGen && !isset($data['_id'])) {
        $data['_id'] = genId();
    }

    $dbConfig = getDbConfig($dbName);
    $dbType = $dbConfig['driver'] ?? 'mysqli';
    if ($dbType === 'auto') {
        $dbType = 'mysqli';
    }

    $keys = array_keys($data);
    $columns = implode(', ', array_map(fn($k) => escapeIdentifier($k, $dbType), $keys));
    $placeholders = implode(', ', array_fill(0, count($keys), '?'));

    $sql = "INSERT INTO " . escapeIdentifier($collection, $dbType) . " ({$columns}) VALUES ({$placeholders})";

    Database::execute($sql, array_values($data), $dbName);
    header('X-SQL-Query: ' . Database::getLastSql());

    return $data;
}

/**
 * Search for documents in a collection
 *
 * @param array $params Operation parameters (collection, search, limit, offset, sort, db)
 * @return array Array of matching documents
 * @throws Exception If required parameters are missing
 */
function find(array $params): array {
    $collection = $params['collection'] ?? null;
    $search = $params['search'] ?? [];
    $limit = $params['limit'] ?? null;
    $offset = $params['offset'] ?? null;
    $sort = $params['sort'] ?? null;
    $dbName = $params['db'] ?? null;

    if (!$collection) {
        throw new Exception("Missing required parameter: collection");
    }

    $dbConfig = getDbConfig($dbName);
    $dbType = $dbConfig['driver'] ?? 'mysqli';
    if ($dbType === 'auto') {
        $dbType = 'mysqli';
    }

    $sql = "SELECT * FROM " . escapeIdentifier($collection, $dbType);

    $whereParams = [];
    $whereClause = buildWhere($search, $whereParams, $dbType);

    if ($whereClause) {
        $sql .= " WHERE " . $whereClause;
    }

    if (is_array($sort)) {
        $orderBy = [];
        foreach ($sort as $field => $direction) {
            $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
            $orderBy[] = escapeIdentifier($field, $dbType) . " {$dir}";
        }
        $sql .= " ORDER BY " . implode(', ', $orderBy);
    }

    if ($limit === null && $params['dbFindOpts']) {
        $limit = $params['dbFindOpts']['limit'] ?? null;
    }

    if ($limit !== null) {
        $sql .= " LIMIT " . intval($limit);
        if ($offset !== null) {
            $sql .= " OFFSET " . intval($offset);
        }
    }

    $results = Database::fetchAll($sql, $whereParams, $dbName);
    header('X-SQL-Query: ' . Database::getLastSql());

    return $results;
}

/**
 * Find a single document in a collection
 *
 * @param array $params Operation parameters (collection, search, db)
 * @return array|null Matching document or null if not found
 * @throws Exception If required parameters are missing
 */
function findOne(array $params): ?array {
    $params['limit'] = 1;
    $results = find($params);
    return !empty($results) ? $results[0] : null;
}

/**
 * Update documents in a collection
 *
 * @param array $params Operation parameters (collection, search, update, db)
 * @param bool $one If true, update only first matching document
 * @return array Array of updated documents
 * @throws Exception If required parameters are missing
 */
function update(array $params, bool $one = false): array {
    $collection = $params['collection'] ?? null;
    $search = $params['search'] ?? [];
    $updateData = $params['update'] ?? [];
    $dbName = $params['db'] ?? null;

    if (!$collection) {
        throw new Exception("Missing required parameter: collection");
    }

    if (empty($updateData)) {
        throw new Exception("Missing required parameter: update");
    }

    $dbConfig = getDbConfig($dbName);
    $dbType = $dbConfig['driver'] ?? 'mysqli';
    if ($dbType === 'auto') {
        $dbType = 'mysqli';
    }

    $selectSql = "SELECT * FROM " . escapeIdentifier($collection, $dbType);
    $whereParams = [];
    $whereClause = buildWhere($search, $whereParams, $dbType);

    if ($whereClause) {
        $selectSql .= " WHERE " . $whereClause;
    }

    if ($one) {
        $selectSql .= " LIMIT 1";
    }

    $matchingDocs = Database::fetchAll($selectSql, $whereParams, $dbName);

    if (empty($matchingDocs)) {
        return [];
    }

    $results = [];

    foreach ($matchingDocs as $doc) {
        $newData = array_merge($doc, $updateData);

        if (isset($updateData['_id']) && $updateData['_id'] !== $doc['_id']) {
            $newData['_id'] = $doc['_id'];
        }

        $keys = array_keys($newData);
        $keyIdIndex = array_search('_id', $keys);
        if ($keyIdIndex !== false) {
            unset($keys[$keyIdIndex]);
        }

        $setClause = implode(', ', array_map(fn($k) => "`{$k}` = ?", $keys));
        $updateValues = array_values(array_filter($newData, fn($k) => $k !== '_id', ARRAY_FILTER_USE_KEY));

        $updateSql = "UPDATE " . escapeIdentifier($collection, $dbType) . " SET {$setClause} WHERE `_id` = ?";
        $updateParams = [...$updateValues, $doc['_id']];

        Database::execute($updateSql, $updateParams, $dbName);

        $updatedDoc = Database::fetchOne(
            "SELECT * FROM " . escapeIdentifier($collection, $dbType) . " WHERE `_id` = ?",
            [$doc['_id']],
            $dbName
        );

        $results[] = $updatedDoc;
    }

    header('X-SQL-Query: ' . Database::getLastSql());
    return $results;
}

/**
 * Update a single document in a collection
 *
 * @param array $params Operation parameters (collection, search, update, db)
 * @return array|null Updated document or null if no match found
 * @throws Exception If required parameters are missing
 */
function updateOne(array $params): ?array {
    $results = update($params, true);
    return !empty($results) ? $results[0] : null;
}

/**
 * Remove documents from a collection
 *
 * @param array $params Operation parameters (collection, search, db)
 * @param bool $one If true, remove only first matching document
 * @return array Array of removed documents
 * @throws Exception If required parameters are missing
 */
function remove(array $params, bool $one = false): array {
    $collection = $params['collection'] ?? null;
    $search = $params['search'] ?? [];
    $dbName = $params['db'] ?? null;

    if (!$collection) {
        throw new Exception("Missing required parameter: collection");
    }

    $dbConfig = getDbConfig($dbName);
    $dbType = $dbConfig['driver'] ?? 'mysqli';
    if ($dbType === 'auto') {
        $dbType = 'mysqli';
    }

    $selectSql = "SELECT * FROM " . escapeIdentifier($collection, $dbType);
    $whereParams = [];
    $whereClause = buildWhere($search, $whereParams, $dbType);

    if ($whereClause) {
        $selectSql .= " WHERE " . $whereClause;
    }

    if ($one) {
        $selectSql .= " LIMIT 1";
    }

    $matchingDocs = Database::fetchAll($selectSql, $whereParams, $dbName);

    if (empty($matchingDocs)) {
        return [];
    }

    $deletedDocs = [];

    foreach ($matchingDocs as $doc) {
        $deleteSql = "DELETE FROM " . escapeIdentifier($collection, $dbType) . " WHERE `_id` = ?";
        Database::execute($deleteSql, [$doc['_id']], $dbName);
        $deletedDocs[] = $doc;
    }

    header('X-SQL-Query: ' . Database::getLastSql());
    return $deletedDocs;
}

/**
 * Remove a single document from a collection
 *
 * @param array $params Operation parameters (collection, search, db)
 * @return array|null Removed document or null if no match found
 * @throws Exception If required parameters are missing
 */
function removeOne(array $params): ?array {
    $results = remove($params, true);
    return !empty($results) ? $results[0] : null;
}
