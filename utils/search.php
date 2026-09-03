<?php
function buildWhere(array $query, array &$params): string {
    if (empty($query)) return '';

    $normalized = [];
    foreach ($query as $key => $value) {
        if (str_starts_with($key, '$')) {
            $normalized[strtolower($key)] = $value;
        } else {
            $normalized[$key] = $value;
        }
    }
    $query = $normalized;

    if (isset($query['$and'])) {
        if (!is_array($query['$and'])) return '';
        $sub = [];
        foreach ($query['$and'] as $v) {
            $w = buildWhere($v, $params);
            if ($w !== '') $sub[] = "($w)";
        }
        return empty($sub) ? '' : implode(' AND ', $sub);
    }

    if (isset($query['$or'])) {
        if (!is_array($query['$or'])) return '';
        $sub = [];
        foreach ($query['$or'] as $v) {
            $w = buildWhere($v, $params);
            if ($w !== '') $sub[] = "($w)";
        }
        return empty($sub) ? '' : implode(' OR ', $sub);
    }

    $parts = [];

    foreach ($query as $key => $value) {
        if (!str_starts_with($key, '$')) {
            $col = escapeIdentifier($key);
            buildCondition($col, $value, $params, $parts, '$eq');
        }
    }

    $notQuery = isset($query['$not']) ? $query['$not'] : null;
    $subsetQuery = isset($query['$subset']) ? $query['$subset'] : null;

    foreach ($query as $key => $value) {
        if (!str_starts_with($key, '$')) continue;
        if ($key === '$and' || $key === '$or') continue;
        if ($key === '$not' || $key === '$subset') continue;
        if (!is_array($value)) continue;
        foreach ($value as $field => $val) {
            $col = escapeIdentifier($field);
            buildCondition($col, $val, $params, $parts, $key);
        }
    }

    if ($notQuery !== null) {
        $w = buildWhere($notQuery, $params);
        if ($w !== '') $parts[] = "NOT ($w)";
    }

    if ($subsetQuery !== null) {
        if (is_array($subsetQuery)) {
            foreach ($subsetQuery as $literalKey => $val) {
                $col = escapeIdentifier($literalKey);
                buildCondition($col, $val, $params, $parts, '$eq');
            }
        }
    }

    return implode(' AND ', $parts);
}

function buildCondition(string $col, $value, array &$params, array &$parts, string $op): void {
    if (is_array($value)) {
        switch ($op) {
            case '$in':   handleIn($col, $value, $params, $parts, false); break;
            case '$nin':  handleIn($col, $value, $params, $parts, true);  break;
            case '$between':
                $parts[] = "$col BETWEEN ? AND ?";
                $params[] = $value[0] ?? null;
                $params[] = $value[1] ?? null;
                break;
            case '$arrincall':
                foreach ($value as $v) {
                    $parts[] = "JSON_CONTAINS($col, ?) = 1";
                    $params[] = json_encode($v, JSON_UNESCAPED_UNICODE);
                }
                break;
            case '$arrinc':
                $sub = [];
                foreach ($value as $v) {
                    $sub[] = "JSON_CONTAINS($col, ?) = 1";
                    $params[] = json_encode($v, JSON_UNESCAPED_UNICODE);
                }
                $parts[] = '(' . implode(' OR ', $sub) . ')';
                break;
            case '$eq':
                $parts[] = "$col = ?";
                $params[] = json_encode($value, JSON_UNESCAPED_UNICODE);
                break;
            case '$ne':
                $parts[] = "$col <> ?";
                $params[] = json_encode($value, JSON_UNESCAPED_UNICODE);
                break;
        }
    } else {
        switch ($op) {
            case '$gt': $parts[] = "$col > ?"; $params[] = $value; break;
            case '$lt': $parts[] = "$col < ?"; $params[] = $value; break;
            case '$gte': $parts[] = "$col >= ?"; $params[] = $value; break;
            case '$lte': $parts[] = "$col <= ?"; $params[] = $value; break;
            case '$eq': buildComparison($col, $value, $params, $parts, '='); break;
            case '$ne': buildComparison($col, $value, $params, $parts, '<>'); break;
            case '$startswith':
                $parts[] = "$col LIKE ?";
                $params[] = $value . '%';
                break;
            case '$endswith':
                $parts[] = "$col LIKE ?";
                $params[] = '%' . $value;
                break;
            case '$istartswith':
                $parts[] = "LOWER($col) LIKE ?";
                $params[] = strtolower($value) . '%';
                break;
            case '$iendswith':
                $parts[] = "LOWER($col) LIKE ?";
                $params[] = '%' . strtolower($value);
                break;
            case '$regex':
                $parts[] = "$col REGEXP ?";
                $params[] = $value;
                break;
            case '$exists':
                $parts[] = $value ? "$col IS NOT NULL" : "$col IS NULL";
                break;
            case '$size':
                $parts[] = "(CHAR_LENGTH($col) = ? OR JSON_LENGTH($col) = ?)";
                $params[] = (int)$value;
                $params[] = (int)$value;
                break;
            case '$arrinc':
                $parts[] = "JSON_CONTAINS($col, ?) = 1";
                $params[] = json_encode($value, JSON_UNESCAPED_UNICODE);
                break;
            case '$idgt': $parts[] = "$col > ?"; $params[] = $value; break;
            case '$idlt': $parts[] = "$col < ?"; $params[] = $value; break;
            case '$idgte': $parts[] = "$col >= ?"; $params[] = $value; break;
            case '$idlte': $parts[] = "$col <= ?"; $params[] = $value; break;
            case '$type':
                $typeVal = strtolower((string)$value);
                switch ($typeVal) {
                    case 'null':
                        $parts[] = "$col IS NULL";
                        break;
                    case 'boolean':
                    case 'bool':
                        $parts[] = "($col = 0 OR $col = 1 OR $col = '0' OR $col = '1')";
                        break;
                    case 'number':
                    case 'integer':
                    case 'int':
                    case 'float':
                    case 'double':
                        $parts[] = "$col REGEXP '^-?[0-9]+(\\.[0-9]+)?$'";
                        break;
                    case 'array':
                        $parts[] = "JSON_TYPE($col) = 'ARRAY'";
                        break;
                    case 'object':
                        $parts[] = "JSON_TYPE($col) = 'OBJECT'";
                        break;
                    case 'string':
                        $parts[] = "$col IS NOT NULL AND $col NOT REGEXP '^-?[0-9]+(\\.[0-9]+)?$' AND $col NOT IN (0, 1) AND JSON_TYPE($col) IS NULL";
                        break;
                }
                break;
            default:
                buildComparison($col, $value, $params, $parts, '=');
                break;
        }
    }
}

function handleIn(string $col, array $values, array &$params, array &$parts, bool $not): void {
    if (empty($values)) {
        $parts[] = $not ? '1=1' : '1=0';
        return;
    }
    $placeholders = implode(',', array_fill(0, count($values), '?'));
    $parts[] = "$col " . ($not ? 'NOT IN' : 'IN') . " ($placeholders)";
    foreach ($values as $v) $params[] = $v;
}

function buildComparison(string $col, $value, array &$params, array &$parts, string $sqlOp): void {
    if ($value === null) {
        $parts[] = $sqlOp === '=' ? "$col IS NULL" : "$col IS NOT NULL";
    } else {
        $parts[] = "$col $sqlOp ?";
        $params[] = $value;
    }
}


