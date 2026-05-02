<?php
require_once __DIR__ . '/../utils/security.php';
require_once __DIR__ . '/../utils/operations.php';

function setDataForUpdateOneOrAdd(array &$query): void
{
    $query['data'] = array_merge(
        assignDataPush($query['search'] ?? []),
        assignDataPush($query['update'] ?? []),
        assignDataPush($query['add_arg'] ?? [])
    );
}

try {
    $params = getRequestParams();

    if (isset($params['collection'])) {
        $result = updateOne($params);

        if ($result !== null) {
            $response = [
                'data' => $result,
                'type' => 'updated'
            ];
        } else {
            setDataForUpdateOneOrAdd($params);
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
