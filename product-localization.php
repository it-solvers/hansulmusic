<?php
declare(strict_types=1);

// [1단계] 지정된 상품 문구를 영어로 바꾸거나 기본 문구를 유지합니다.
/** 등록된 영어 번역을 적용하고, 번역이 없으면 상품 원문을 반환합니다. */
function englishProductCopy(int $productType, string $name, string $subtitle, string $description): array
{
    // [2단계] 영문 전용 문구는 상품 종류(products.product_type)를 키로 대응합니다.
    // 키는 DB 값(1=악보, 2=음원)과 같고, 상품 관리 화면(admin-products.php)의 상품 종류 선택지와 동일합니다.
    $translations = [
        1 => [
            'name' => '8.1.3. Cantata: Glory, Glory',
            'subtitle' => 'Orchestral Score and Parts',
            'description' => 'Preview selected pages from the orchestral score.',
        ],
        2 => [
            'name' => 'I Wish the Wind Would Blow',
            'subtitle' => 'Gilgu Bonggu · MP3',
            'description' => 'Listen to a one-minute preview of this recording.',
        ],
    ];

    // [3단계] 번역 항목이 없는 상품은 전달받은 원문 필드를 그대로 반환합니다.
    return $translations[$productType] ?? [
        'name' => $name,
        'subtitle' => $subtitle,
        'description' => $description,
    ];
}
