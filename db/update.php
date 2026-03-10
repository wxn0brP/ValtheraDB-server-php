<?php
/**
 * Update operation handler - update documents in a collection
 * Supports POST requests with advanced search operators
 *
 * API Format:
 *   POST: /db/update (JSON: {"db": "mainDB", "params": [{"collection": "users", "search": {"name": "John"}, "update": {"age": 31}}]})
 *
 * Search operators (same as find.php):
 *   - Exact match: {"name": "John"}
 *   - Comparison: {"age": {"$gt": 18}}
 *   - Range: {"age": {"$between": [18, 65]}}
 *   - Lists: {"status": {"$in": ["active"]}}
 *   - Patterns: {"name": {"$startswith": "Jo"}}
 *   - Regex: {"email": {"$regex": ".*@gmail.com"}}
 *   - Logical: {"$and": [...], "$or": [...]}
 *
 * Additional params:
 *   - one: if true, update only first matching document
 */

require_once __DIR__ . '/../utils/security.php';
require_once __DIR__ . '/../utils/operations.php';

try {
    $params = getRequestParams();

    if (isset($params['collection'])) {
        $one = $params['one'] ?? false;
        $results = update($params, $one);
        jsonResponse($results);
    }
} catch (Throwable $e) {
    errorResponse($e->getMessage(), 400);
}
