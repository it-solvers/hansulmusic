<?php
declare(strict_types=1);

function englishProductCopy(string $slug, string $name, string $subtitle, string $description): array
{
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

    return $translations[$slug] ?? [
        'name' => $name,
        'subtitle' => $subtitle,
        'description' => $description,
    ];
}
