<?php

use App\Support\Articles\ReadingTime;

/**
 * Build a rich text block holding a run of $length readable characters.
 *
 * @return array{type: string, data: array{content: string}}
 */
function richTextOf(int $length): array
{
    return ['type' => 'richText', 'data' => ['content' => '<p>'.str_repeat('a', $length).'</p>']];
}

test('an article with no content still reads as a minute', function () {
    expect(ReadingTime::minutes([], 1000))->toBe(1)
        ->and(ReadingTime::minutes(null, 1000))->toBe(1);
});

test('the estimate rounds up to the next whole minute', function () {
    expect(ReadingTime::minutes([richTextOf(1000)], 1000))->toBe(1)
        ->and(ReadingTime::minutes([richTextOf(1001)], 1000))->toBe(2)
        ->and(ReadingTime::minutes([richTextOf(8000)], 1000))->toBe(8);
});

test('a slower reading speed quotes a longer time for the same article', function () {
    expect(ReadingTime::minutes([richTextOf(2000)], 1000))->toBe(2)
        ->and(ReadingTime::minutes([richTextOf(2000)], 500))->toBe(4);
});

test('markup is not counted as text', function () {
    $plain = [['type' => 'richText', 'data' => ['content' => 'Vitamin D']]];
    $marked = [['type' => 'richText', 'data' => ['content' => '<p class="lead"><strong>Vitamin</strong> <em>D</em></p>']]];

    expect(ReadingTime::characters($marked))->toBe(ReadingTime::characters($plain));
});

test('entities count as the character they stand for', function () {
    expect(ReadingTime::characters([['type' => 'richText', 'data' => ['content' => 'D&amp;D']]]))->toBe(3);
});

test('adjacent blocks of markup do not run their words together', function () {
    expect(ReadingTime::characters([['type' => 'richText', 'data' => ['content' => '<p>one</p><p>two</p>']]]))
        ->toBe(mb_strlen('one two'));
});

test('headings and faq pairs are counted alongside body copy', function () {
    $content = [
        ['type' => 'h2', 'data' => ['text' => str_repeat('a', 100)]],
        richTextOf(200),
        ['type' => 'faq', 'data' => [
            'heading' => str_repeat('a', 50),
            'items' => [
                ['question' => str_repeat('a', 25), 'answer' => '<p>'.str_repeat('a', 125).'</p>'],
            ],
        ]],
    ];

    expect(ReadingTime::characters($content))->toBe(500);
});

test('captions and product cards are skipped, being scanned rather than read', function () {
    $content = [
        ['type' => 'image', 'data' => ['image' => 'a.webp', 'alt' => str_repeat('a', 200), 'caption' => str_repeat('a', 200)]],
        ['type' => 'productCard', 'data' => ['product_id' => 1, 'description' => str_repeat('a', 2000)]],
    ];

    expect(ReadingTime::characters($content))->toBe(0);
});

test('malformed blocks are ignored rather than fatal', function () {
    $content = [
        'not a block',
        ['type' => 'richText'],
        ['type' => 'h2', 'data' => ['text' => null]],
        ['type' => 'faq', 'data' => ['items' => ['not an item']]],
        richTextOf(300),
    ];

    expect(ReadingTime::characters($content))->toBe(300);
});
