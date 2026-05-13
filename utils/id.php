<?php
function genId(): string
{
    $time = base_convert((string) (microtime(true) * 1000), 10, 36);

    $part1 = base_convert((string) rand(0, 35), 10, 36);
    $part2 = base_convert((string) rand(0, 35), 10, 36);

    return "{$time}-{$part1}-{$part2}";
}
