<?php
require_once __DIR__ . '/../utils/security.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/utils.php';

function ensureCollectionCheck(string $collection): bool
{
    global $_DB_DRIVER;
    $driver = $_DB_DRIVER ?? 'mysql';
    $checkSql = "SHOW TABLES LIKE '" . $collection . "'";
    $result = db_fetch_all($checkSql, []);
    return !empty($result);
}

function ensureCollection(string $collection): bool
{
    global $_DB_DRIVER;

    if (ensureCollectionCheck($collection)) {
        return false;
    }

    $driver = $_DB_DRIVER ?? 'mysql';
    $sql = 'CREATE TABLE IF NOT EXISTS ' . escapeIdentifier($collection, $driver) . ' (_id VARCHAR(64) PRIMARY KEY)';
    db_execute($sql, []);
    return true;
}

try {
    $params = getRequestParams();
    $collection = $params['collection'] ?? null;
    $dbName = $params['db'] ?? null;

    if ($collection) {
        $dbConfig = getDbConfig($dbName);
        db_init($dbConfig);

        $result = ensureCollection($collection);
        db_close();
        jsonResponse($result);
    } else {
        jsonErrResponse('Missing required parameter: collection', 400);
    }
} catch (Throwable $e) {
    error_log('[ensureCollection.php] ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    error_log('[ensureCollection.php] Stack trace: ' . $e->getTraceAsString());
    errorResponse($e->getMessage());
}
