<?php

use App\Models\Article;
use App\Support\Images\ArticleImageProcessor;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->disk = Storage::disk('public');
    $this->processor = app(ArticleImageProcessor::class);
});

/**
 * Build an image content block pointing at the given stored path.
 *
 * @return array{type: string, data: array{image: string, alt: string, caption: ?string}}
 */
function imageBlock(string $path): array
{
    return [
        'type' => 'image',
        'data' => ['image' => $path, 'alt' => 'Alt text', 'caption' => null],
    ];
}

test('a tmp upload is converted into webp variants and the content path is rewritten', function () {
    $this->disk->put('articles/tmp/photo-tmpabc123.jpg', fakeJpegImage(1200, 800));

    $article = Article::factory()->create([
        'content' => [imageBlock('articles/tmp/photo-tmpabc123.jpg')],
    ]);

    $converted = $this->processor->process($article);

    $article->refresh();

    expect($converted)->toBeTrue()
        ->and($article->content[0]['data']['image'])->toBe("articles/{$article->id}/photo.webp")
        ->and($this->disk->exists("articles/{$article->id}/photo.webp"))->toBeTrue()
        ->and($this->disk->exists("articles/{$article->id}/photo-desktop.webp"))->toBeTrue()
        ->and($this->disk->exists("articles/{$article->id}/photo-mobile.webp"))->toBeTrue()
        ->and($this->disk->exists("articles/{$article->id}/photo-thumbnail.webp"))->toBeTrue()
        ->and($this->disk->exists('articles/tmp/photo-tmpabc123.jpg'))->toBeFalse();
});

test('rich text blocks are preserved when image blocks are processed', function () {
    $this->disk->put('articles/tmp/photo-tmpabc123.jpg', fakeJpegImage(600, 400));

    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => '<p>Hello</p>']],
            imageBlock('articles/tmp/photo-tmpabc123.jpg'),
        ],
    ]);

    $this->processor->process($article);

    $article->refresh();

    expect($article->content[0])->toBe(['type' => 'richText', 'data' => ['content' => '<p>Hello</p>']])
        ->and($article->content[1]['data']['image'])->toBe("articles/{$article->id}/photo.webp")
        ->and($article->content[1]['data']['alt'])->toBe('Alt text');
});

test('faq blocks are preserved when image blocks are processed', function () {
    $this->disk->put('articles/tmp/photo-tmpabc123.jpg', fakeJpegImage(600, 400));

    $faqBlock = [
        'type' => 'faq',
        'data' => [
            'heading' => 'FAQ',
            'items' => [['question' => 'What?', 'answer' => '<p>This.</p>']],
        ],
    ];

    $article = Article::factory()->create([
        'content' => [
            $faqBlock,
            imageBlock('articles/tmp/photo-tmpabc123.jpg'),
        ],
    ]);

    $this->processor->process($article);

    $article->refresh();

    expect($article->content[0])->toBe($faqBlock)
        ->and($article->content[1]['data']['image'])->toBe("articles/{$article->id}/photo.webp");
});

test('processing is idempotent for already converted blocks', function () {
    $this->disk->put('articles/tmp/photo-tmpabc123.jpg', fakeJpegImage(600, 400));

    $article = Article::factory()->create([
        'content' => [imageBlock('articles/tmp/photo-tmpabc123.jpg')],
    ]);

    $this->processor->process($article);

    $contentAfterFirstRun = $article->refresh()->content;

    $secondRunConverted = $this->processor->process($article);

    expect($secondRunConverted)->toBeFalse()
        ->and($article->refresh()->content)->toBe($contentAfterFirstRun)
        ->and($this->disk->exists("articles/{$article->id}/photo.webp"))->toBeTrue();
});

test('a name collision within the article folder gets a numeric suffix', function () {
    $this->disk->put('articles/tmp/photo-tmpaaa111.jpg', fakeJpegImage(600, 400));
    $this->disk->put('articles/tmp/photo-tmpbbb222.jpg', fakeJpegImage(500, 300));

    $article = Article::factory()->create([
        'content' => [
            imageBlock('articles/tmp/photo-tmpaaa111.jpg'),
            imageBlock('articles/tmp/photo-tmpbbb222.jpg'),
        ],
    ]);

    $this->processor->process($article);

    $article->refresh();

    expect($article->content[0]['data']['image'])->toBe("articles/{$article->id}/photo.webp")
        ->and($article->content[1]['data']['image'])->toBe("articles/{$article->id}/photo-1.webp")
        ->and($this->disk->exists("articles/{$article->id}/photo-1-desktop.webp"))->toBeTrue();
});

test('orphaned image sets are deleted when no block references them', function () {
    $article = Article::factory()->create([
        'content' => [['type' => 'richText', 'data' => ['content' => '<p>Text only</p>']]],
    ]);

    foreach (['photo.webp', 'photo-desktop.webp', 'photo-mobile.webp', 'photo-thumbnail.webp'] as $file) {
        $this->disk->put("articles/{$article->id}/{$file}", 'stale');
    }

    $this->processor->process($article);

    expect($this->disk->files("articles/{$article->id}"))->toBe([]);
});

test('referenced image sets survive cleanup while replaced ones are removed', function () {
    $this->disk->put('articles/tmp/new-tmpccc333.jpg', fakeJpegImage(600, 400));

    $article = Article::factory()->create([
        'content' => [imageBlock('articles/tmp/new-tmpccc333.jpg')],
    ]);

    foreach (['old.webp', 'old-desktop.webp', 'old-mobile.webp', 'old-thumbnail.webp'] as $file) {
        $this->disk->put("articles/{$article->id}/{$file}", 'stale');
    }

    $this->processor->process($article);

    expect($this->disk->exists("articles/{$article->id}/new.webp"))->toBeTrue()
        ->and($this->disk->exists("articles/{$article->id}/old.webp"))->toBeFalse()
        ->and($this->disk->exists("articles/{$article->id}/old-desktop.webp"))->toBeFalse();
});

test('a corrupt upload is left untouched and does not break processing', function () {
    $this->disk->put('articles/tmp/bad-tmpddd444.jpg', 'not-an-image');

    $article = Article::factory()->create([
        'content' => [imageBlock('articles/tmp/bad-tmpddd444.jpg')],
    ]);

    $converted = $this->processor->process($article);

    $article->refresh();

    expect($converted)->toBeFalse()
        ->and($article->content[0]['data']['image'])->toBe('articles/tmp/bad-tmpddd444.jpg')
        ->and($this->disk->exists('articles/tmp/bad-tmpddd444.jpg'))->toBeTrue();
});

test('deleting an article removes its image directory', function () {
    $article = Article::factory()->create();

    $this->disk->put("articles/{$article->id}/photo.webp", 'image');

    $article->delete();

    expect($this->disk->exists("articles/{$article->id}/photo.webp"))->toBeFalse()
        ->and($this->disk->directories('articles'))->not->toContain("articles/{$article->id}");
});
