<?php

namespace Project\tests\Utils;

function joinPath(string ...$parts): string
{
    return implode(DIRECTORY_SEPARATOR, $parts);
}

function getFixturePath(string ...$parts): string
{
    return joinPath(__DIR__, 'fixtures', ...$parts);
}
