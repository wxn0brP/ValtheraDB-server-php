<?php
/**
 * Check if collection exists
 *
 * Usage:
 *   GET:  /issetCollection?collection=users
 *   POST: /issetCollection (JSON body: {"collection": "users"})
 */

require_once __DIR__ . '/../utils/security.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/utils.php';

/**
 * Check if collection exists
 */
function issetCollection(string $collection, ?string $dbName = null): bool {
    return Database::tableExists($collection, $dbName);
}

// Handle direct API call
try {
    $params = getRequestParams();
    $collection = $params['collection'] ?? null;
    $dbName = $params['db'] ?? null;

    if (!$collection) {
        throw new Exception("Missing required parameter: collection");
    }

    jsonResponse(issetCollection($collection, $dbName));

} catch (Throwable $e) {
    error_log('[issetCollection.php] ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    error_log('[issetCollection.php] Stack trace: ' . $e->getTraceAsString());
    errorResponse($e->getMessage());
}
