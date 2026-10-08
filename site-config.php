<?php
declare(strict_types=1);

function appBasePath(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $basePath = dirname($scriptName);
    return $basePath === '/' || $basePath === '.' ? '' : '/' . trim($basePath, '/');
}
