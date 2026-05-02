<?php
require_once __DIR__ . '/../utils/security.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/utils.php';

function issetCollection(string $collection, string $dbName): bool
{
    $sql = 'SHOW TABLES LIKE ?';
    $result = db_fetch_all($sql, [$dbName]);
    return count($result) > 0;
}

try {
    $params = getRequestParams();
    $collection = $params['collection'] ?? null;
    $dbName = $params['db'] ?? null;

    if ($collection) {
        $dbConfig = getDbConfig($dbName);
        db_init($dbConfig);
        $isset = issetCollection($collection, $dbName);

        if ($isset)
            jsonResponse(true);
        else
            jsonErrResponse('Collection does not exist', 400);
    } else {
        jsonErrResponse('Missing required parameter: collection', 400);
    }
} catch (Throwable $e) {
    error_log('[ensureCollection.php] ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    error_log('[ensureCollection.php] Stack trace: ' . $e->getTraceAsString());
    errorResponse($e->getMessage());
}
