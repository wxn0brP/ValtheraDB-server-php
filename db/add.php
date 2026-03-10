<?php
/**
 * Add operation handler - insert a new document into a collection
 * Supports POST requests
 *
 * API Format:
 *   POST: /db/add (JSON: {"db": "mainDB", "params": [{"collection": "users", "data": {"name": "John", "email": "john@example.com"}}]})
 */

require_once __DIR__ . '/../utils/security.php';
require_once __DIR__ . '/../utils/operations.php';

try {
    $params = getRequestParams();

    if (isset($params['collection'])) {
        $result = add($params);
        jsonResponse($result);
    }
} catch (Throwable $e) {
    errorResponse($e->getMessage(), 400);
}
