<?php
function applyUpdater(array $doc, array $updater): array
{
    if (empty($updater)) {
        return $doc;
    }

    $result = $doc;
    $ops = [];
    $direct = [];

    foreach ($updater as $key => $value) {
        if (is_string($key) && str_starts_with($key, '$')) {
            $ops[strtolower($key)] = $value;
        } else {
            $direct[$key] = $value;
        }
    }

    if (!empty($ops)) {
        applyUpdaterOps($result, $ops, [
            'push' => function (array &$target, $key, $value): void {
                $target[$key] ??= [];
                $target[$key][] = $value;
            },
            'pushall' => function (array &$target, $key, $value): void {
                if (isset($target[$key]) && is_array($target[$key])) {
                    if (is_array($value)) {
                        array_push($target[$key], ...$value);
                    }
                } else {
                    $target[$key] = is_array($value) ? $value : [$value];
                }
            },
            'pushset' => function (array &$target, $key, $value): void {
                $target[$key] ??= [];
                if (!in_array($value, $target[$key], true)) {
                    $target[$key][] = $value;
                }
            },
            'pull' => function (array &$target, $key, $value): void {
                if (isset($target[$key]) && is_array($target[$key])) {
                    $target[$key] = array_values(
                        array_filter($target[$key], fn($item) => $item !== $value)
                    );
                }
            },
            'pullall' => function (array &$target, $key, $value): void {
                if (isset($target[$key]) && is_array($target[$key]) && is_array($value)) {
                    $target[$key] = array_values(array_diff($target[$key], $value));
                }
            },
            'merge' => function (array &$target, $key, $value): void {
                if (is_array($value)) {
                    $target[$key] = isset($target[$key]) && is_array($target[$key])
                        ? array_merge($target[$key], $value)
                        : $value;
                }
            },
            'inc' => function (array &$target, $key, $value): void {
                if (array_key_exists($key, $target)) {
                    if (!is_numeric($target[$key])) {
                        throw new \Exception("Cannot increment non-numeric value at key: {$key}");
                    }
                    $target[$key] += (is_numeric($value) ? $value : 0);
                } else {
                    $target[$key] = is_numeric($value) ? $value : 0;
                }
            },
            'dec' => function (array &$target, $key, $value): void {
                if (array_key_exists($key, $target)) {
                    if (!is_numeric($target[$key])) {
                        throw new \Exception("Cannot decrement non-numeric value at key: {$key}");
                    }
                    $target[$key] -= (is_numeric($value) ? $value : 0);
                } else {
                    $target[$key] = -(is_numeric($value) ? $value : 0);
                }
            },
            'rename' => function (array &$target, $key, $value): void {
                if (is_string($value) && array_key_exists($key, $target)) {
                    $target[$value] = $target[$key];
                    unset($target[$key]);
                }
            },
            'set' => function (array &$target, $key, $value): void {
                $target[$key] = $value;
            },
            'unset' => function (array &$target, $key, $_): void {
                if (array_key_exists($key, $target)) {
                    unset($target[$key]);
                }
            },
        ]);

        if (array_key_exists('$deepmerge', $ops) && is_array($ops['$deepmerge'])) {
            $result = empty($result)
                ? $ops['$deepmerge']
                : array_merge_recursive($result, $ops['$deepmerge']);
        }
    }

    foreach ($direct as $key => $value) {
        $result[$key] = $value;
    }

    return $result;
}

function applyUpdaterOps(array &$obj, array $ops, array $handlers): void
{
    foreach ($handlers as $opName => $handler) {
        $opKey = '$' . $opName;
        if (!array_key_exists($opKey, $ops))
            continue;

        $fields = $ops[$opKey];
        if (!is_array($fields))
            continue;

        foreach ($fields as $key => $value) {
            if (is_array($value) && !array_is_list($value)) {
                if (!isset($obj[$key]) || !is_array($obj[$key])) {
                    $obj[$key] = [];
                }
                deepUpdateCheck($value, $obj[$key], $handler);
            } else {
                $handler($obj, $key, $value);
            }
        }
    }
}

function deepUpdateCheck(array $valueObj, array &$targetObj, callable $handler): void
{
    foreach ($valueObj as $k => $v) {
        if (is_array($v) && !array_is_list($v)) {
            if (!isset($targetObj[$k]) || !is_array($targetObj[$k])) {
                $targetObj[$k] = [];
            }
            deepUpdateCheck($v, $targetObj[$k], $handler);
        } else {
            $handler($targetObj, $k, $v);
        }
    }
}
