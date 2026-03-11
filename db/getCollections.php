<?php
/**
 * Get list of all collections
 *
 * Usage:
 *   GET:  /getCollections
 *   POST: /getCollections
 */

require_once __DIR__ . '/../utils/security.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/utils.php';

/**
 * Get list of all collections
 */
function getCollections(?string $dbName = null): array
{
    $sql = "SHOW TABLES";
    $tables = db_fetch_all($sql, []);
    return array_column($tables, array_keys($tables[0])[0]);
}

// Handle direct API call
try {
    $params = getRequestParams();
    $dbName = $params['db'] ?? null;
    $dbConfig = getDbConfig($dbName);
    db_init($dbConfig);
    jsonResponse(getCollections($dbName));
    db_close();

} catch (Throwable $e) {
    error_log('[getCollections.php] ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    errorResponse($e->getMessage());
}
