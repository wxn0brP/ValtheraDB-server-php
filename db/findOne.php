<?php
/**
 * FindOne operation handler - find a single document in a collection
 * Supports GET and POST requests with advanced search operators
 *
 * API Format:
 *   GET:  /db/findOne?collection=users&search={"name":"John"}
 *   POST: /db/findOne (JSON: {"db": "mainDB", "params": [{"collection": "users", "search": {"name": "John"}}]})
 *
 * Search operators:
 *   - Exact match: {"name": "John"}
 *   - Comparison: {"age": {"$gt": 18}}, {"age": {"$lt": 65}}
 *   - Range: {"age": {"$between": [18, 65]}}
 *   - Lists: {"status": {"$in": ["active", "pending"]}}
 *   - Patterns: {"name": {"$startswith": "Jo"}}, {"name": {"$endswith": "hn"}}
 *   - Regex: {"email": {"$regex": ".*@gmail.com"}}
 *   - Logical: {"$and": [{"age": {"$gt": 18}}, {"status": "active"}]}
 */

require_once __DIR__ . '/../utils/security.php';
require_once __DIR__ . '/../utils/operations.php';

try {
    $params = getRequestParams();

    if (isset($params['collection'])) {
        $result = findOne($params);
        jsonResponse($result);
    }
} catch (Throwable $e) {
    errorResponse($e->getMessage(), 400);
}
