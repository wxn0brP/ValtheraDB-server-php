<?php
/**
 * Search utilities - WHERE clause building with advanced operators
 * Supports: $gt, $lt, $gte, $lte, $in, $nin, $startswith, $endswith, $regex, $between, $and, $or
 */

/**
 * Build WHERE clause with advanced operators support
 * Format: { $op: { key: value } } e.g. ["$gt" => ["age" => 18]]
 */
function buildWhere(array $query, array &$params, string $type): string {
    if (!is_array($query)) {
        return "";
    }

    $parts = [];

    foreach ($query as $op => $condition) {
        if ($op === '$and') {
            $sub = [];
            foreach ($condition as $v) {
                $sub[] = "(" . buildWhere($v, $params, $type) . ")";
            }
            $parts[] = implode(" AND ", $sub);
            continue;
        }

        if ($op === '$or') {
            $sub = [];
            foreach ($condition as $v) {
                $sub[] = "(" . buildWhere($v, $params, $type) . ")";
            }
            $parts[] = implode(" OR ", $sub);
            continue;
        }

        if (!is_array($condition)) {
            continue;
        }

        foreach ($condition as $key => $value) {
            $col = escapeIdentifier($key, $type);

            if (is_array($value)) {
                switch ($op) {
                    case '$in':
                        $placeholders = implode(',', array_fill(0, count($value), '?'));
                        $parts[] = "$col IN ($placeholders)";
                        foreach ($value as $v) {
                            $params[] = $v;
                        }
                        break;

                    case '$nin':
                        $placeholders = implode(',', array_fill(0, count($value), '?'));
                        $parts[] = "$col NOT IN ($placeholders)";
                        foreach ($value as $v) {
                            $params[] = $v;
                        }
                        break;

                    case '$between':
                        $parts[] = "$col BETWEEN ? AND ?";
                        $params[] = $value[0];
                        $params[] = $value[1];
                        break;
                }
            } else {
                switch ($op) {
                    case '$gt':
                        $parts[] = "$col > ?";
                        $params[] = $value;
                        break;

                    case '$lt':
                        $parts[] = "$col < ?";
                        $params[] = $value;
                        break;

                    case '$gte':
                        $parts[] = "$col >= ?";
                        $params[] = $value;
                        break;

                    case '$lte':
                        $parts[] = "$col <= ?";
                        $params[] = $value;
                        break;

                    case '$startswith':
                        $parts[] = "$col LIKE ?";
                        $params[] = $value . "%";
                        break;

                    case '$endswith':
                        $parts[] = "$col LIKE ?";
                        $params[] = "%" . $value;
                        break;

                    case '$regex':
                        if ($type === "postgres") {
                            $parts[] = "$col ~ ?";
                        } else {
                            $parts[] = "$col REGEXP ?";
                        }
                        $params[] = $value;
                        break;

                    default:
                        $parts[] = "$col = ?";
                        $params[] = $value;
                        break;
                }
            }
        }
    }

    return implode(" AND ", $parts);
}
