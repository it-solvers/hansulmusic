<?php
declare(strict_types=1);

// [1단계] 현재 요청 스크립트 위치에서 사이트의 기준 경로를 계산합니다.
/** 현재 스크립트 위치에서 사이트의 정규화된 기준 URL 경로를 반환합니다. */
function appBasePath(): string
{
    // [2단계] 서버 경로의 구분자를 통일한 다음 스크립트가 속한 디렉터리를 구합니다.
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $basePath = dirname($scriptName);
    // [3단계] 루트 설치는 빈 접두사로 두고, 하위 설치 경로에는 슬래시를 하나만 붙입니다.
    return $basePath === '/' || $basePath === '.' ? '' : '/' . trim($basePath, '/');
}
