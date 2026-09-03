<?php
require_once __DIR__ . '/../utils/security.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/utils.php';

function removeCollection(string $collection): bool
{
    $sql = 'DROP TABLE IF EXISTS ' . escapeIdentifier($collection);
    return db_execute($sql, []);
}

try {
    $params = getRequestParams();
    $collection = $params['collection'] ?? null;
    $dbName = $params['db'] ?? null;

    if ($collection) {
        $dbConfig = getDbConfig($dbName);
        db_init($dbConfig);

        $result = removeCollection($collection);
        jsonResponse($result);
        db_close();
    } else {
        jsonErrResponse('Missing required parameter: collection', 400);
    }
} catch (Throwable $e) {
    error_log('[removeCollection.php] ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    error_log('[removeCollection.php] Stack trace: ' . $e->getTraceAsString());
    errorResponse($e->getMessage());
}
