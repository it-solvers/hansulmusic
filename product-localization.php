<?php
declare(strict_types=1);

// [1단계] 지정된 상품 문구를 영어로 바꾸거나 기본 문구를 유지합니다.
/** 등록된 영어 번역을 적용하고, 번역이 없으면 상품 원문을 반환합니다. */
function englishProductCopy(string $slug, string $name, string $subtitle, string $description): array
{
    // [2단계] 영문 전용 문구가 정의된 상품만 슬러그 기준으로 대응합니다.
    $translations = [
        'score-glory' => [
            'name' => '8.1.3. Cantata: Glory, Glory',
            'subtitle' => 'Orchestral Score and Parts',
            'description' => 'Preview selected pages from the orchestral score.',
        ],
        'audio-featured' => [
            'name' => 'I Wish the Wind Would Blow',
            'subtitle' => 'Gilgu Bonggu · MP3',
            'description' => 'Listen to a one-minute preview of this recording.',
        ],
    ];

    // [3단계] 번역 항목이 없는 상품은 전달받은 원문 필드를 그대로 반환합니다.
    return $translations[$slug] ?? [
        'name' => $name,
        'subtitle' => $subtitle,
        'description' => $description,
    ];
}
