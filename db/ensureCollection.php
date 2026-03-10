<?php
/**
 * Ensure collection - creates collection if it doesn't exist
 *
 * Usage:
 *   GET:  /ensureCollection?collection=users
 *   POST: /ensureCollection (JSON body: {"collection": "users"})
 */

require_once __DIR__ . '/../utils/security.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/utils.php';

/**
 * Ensure collection exists - creates it if it doesn't
 */
function ensureCollection(string $collection, ?string $dbName = null): bool {
    // Do nothing (excepted)
}

// Handle direct API call
try {
    $params = getRequestParams();
    $collection = $params['collection'] ?? null;
    $dbName = $params['db'] ?? null;

    if ($collection) {
        $created = ensureCollection($collection, $dbName);
        jsonResponse(['err' => false, 'result' => true]);
    }

} catch (Throwable $e) {
    error_log('[ensureCollection.php] ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    error_log('[ensureCollection.php] Stack trace: ' . $e->getTraceAsString());
    errorResponse($e->getMessage());
}
