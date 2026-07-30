<?php

use App\Support\Articles\ArticleHeadings;

test('headings are extracted in document order keyed by block position', function () {
    $headings = ArticleHeadings::extract([
        ['type' => 'richText', 'data' => ['content' => '<p>Intro</p>']],
        ['type' => 'h2', 'data' => ['text' => 'How it works']],
        ['type' => 'richText', 'data' => ['content' => '<p>Body</p>']],
        ['type' => 'h2', 'data' => ['text' => 'What to buy']],
    ]);

    expect($headings)->toBe([
        1 => ['id' => 'how-it-works', 'text' => 'How it works'],
        3 => ['id' => 'what-to-buy', 'text' => 'What to buy'],
    ]);
});

test('blocks that are not headings and headings without text are skipped', function () {
    $headings = ArticleHeadings::extract([
        ['type' => 'image', 'data' => ['image' => 'articles/1/photo.webp', 'alt' => 'A photo']],
        ['type' => 'h2', 'data' => ['text' => '']],
        ['type' => 'h2', 'data' => []],
        ['type' => 'h2'],
        ['type' => 'h2', 'data' => ['text' => 'Real heading']],
    ]);

    expect($headings)->toBe([
        4 => ['id' => 'real-heading', 'text' => 'Real heading'],
    ]);
});

test('repeated headings get unique anchor ids', function () {
    $headings = ArticleHeadings::extract([
        ['type' => 'h2', 'data' => ['text' => 'Dosage']],
        ['type' => 'h2', 'data' => ['text' => 'Dosage']],
        ['type' => 'h2', 'data' => ['text' => 'Dosage']],
    ]);

    expect(array_column($headings, 'id'))->toBe(['dosage', 'dosage-2', 'dosage-3']);
});

test('cyrillic headings are transliterated into anchor ids', function () {
    $headings = ArticleHeadings::extract([
        ['type' => 'h2', 'data' => ['text' => 'Как принимать']],
    ]);

    expect($headings)->toBe([
        0 => ['id' => 'kak-prinimat', 'text' => 'Как принимать'],
    ]);
});

test('a heading that slugifies to nothing still gets an anchor id', function () {
    $headings = ArticleHeadings::extract([
        ['type' => 'h2', 'data' => ['text' => '💊']],
        ['type' => 'h2', 'data' => ['text' => '— ?! —']],
    ]);

    expect(array_column($headings, 'id'))->toBe(['section', 'section-2'])
        ->and($headings[0]['text'])->toBe('💊');
});

test('empty and null content produce no headings', function () {
    expect(ArticleHeadings::extract([]))->toBe([])
        ->and(ArticleHeadings::extract(null))->toBe([]);
});
