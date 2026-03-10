<?php
/**
 * RemoveOne operation handler - delete a single document from a collection
 * Supports POST requests with advanced search operators
 *
 * API Format:
 *   POST: /db/removeOne (JSON: {"db": "mainDB", "params": [{"collection": "users", "search": {"name": "John"}}]})
 *
 * Search operators (same as find.php):
 *   - Exact match: {"name": "John"}
 *   - Comparison: {"age": {"$gt": 18}}
 *   - Range: {"age": {"$between": [18, 65]}}
 *   - Lists: {"status": {"$in": ["active"]}}
 *   - Patterns: {"name": {"$startswith": "Jo"}}
 *   - Regex: {"email": {"$regex": ".*@gmail.com"}}
 *   - Logical: {"$and": [...], "$or": [...]}
 */

require_once __DIR__ . '/../utils/security.php';
require_once __DIR__ . '/../utils/operations.php';

try {
    $params = getRequestParams();

    if (isset($params['collection'])) {
        $result = removeOne($params);
        jsonResponse($result);
    }
} catch (Throwable $e) {
    errorResponse($e->getMessage(), 400);
}
