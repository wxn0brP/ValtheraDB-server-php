<?php
function applyFindOpts(array $doc, array $findOpts): array
{
    $result = $doc;

    if (!empty($findOpts['exclude']) && is_array($findOpts['exclude'])) {
        foreach ($findOpts['exclude'] as $field) {
            if (is_string($field) && array_key_exists($field, $result)) {
                unset($result[$field]);
            }
        }
    }

    if (!empty($findOpts['select']) && is_array($findOpts['select'])) {
        $filtered = [];
        foreach ($findOpts['select'] as $field) {
            if (is_string($field) && array_key_exists($field, $result)) {
                $filtered[$field] = $result[$field];
            }
        }
        return $filtered;
    }

    return $result;
}
