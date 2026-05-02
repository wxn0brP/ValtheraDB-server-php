<?php
require_once __DIR__ . '/../utils/security.php';
require_once __DIR__ . '/../utils/operations.php';

function setDataForToggleOne(array &$query): void
{
    $query['data'] = array_merge(
        assignDataPush($query['search'] ?? []),
        assignDataPush($query['data'] ?? [])
    );
}

try {
    $params = getRequestParams();

    if (isset($params['collection'])) {
        $result = removeOne($params);

        if ($result !== null) {
            $response = [
                'data' => $result,
                'type' => 'removed'
            ];
        } else {
            setDataForToggleOne($params);
            $response = [
                'data' => add($params),
                'type' => 'added'
            ];
        }

        jsonResponse($response);
    }
} catch (Throwable $e) {
    errorResponse($e->getMessage(), 400);
}
