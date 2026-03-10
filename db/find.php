<?php
/**
 * Find operation handler - search for documents in a collection
 * Supports GET and POST requests with advanced search operators
 *
 * API Format:
 *   GET:  /db/find?collection=users&search={"name":"John"}
 *   POST: /db/find (JSON: {"db": "mainDB", "params": [{"collection": "users", "search": {"name": "John"}}]})
 *
 * Search operators:
 *   - Exact match: {"name": "John"}
 *   - Comparison: {"age": {"$gt": 18}}, {"age": {"$lt": 65}}
 *   - Range: {"age": {"$between": [18, 65]}}
 *   - Lists: {"status": {"$in": ["active", "pending"]}}
 *   - Patterns: {"name": {"$startswith": "Jo"}}, {"name": {"$endswith": "hn"}}
 *   - Regex: {"email": {"$regex": ".*@gmail.com"}}
 *   - Logical: {"$and": [{"age": {"$gt": 18}}, {"status": "active"}]}
 *
 * Additional params:
 *   - limit: max results count
 *   - offset: results offset
 *   - sort: {"field": "ASC"|"DESC"}
 */

require_once __DIR__ . '/../utils/security.php';
require_once __DIR__ . '/../utils/operations.php';

try {
    $params = getRequestParams();

    if (isset($params['collection'])) {
        $results = find($params);
        jsonResponse($results);
    }
} catch (Throwable $e) {
    errorResponse($e->getMessage(), 400);
}
