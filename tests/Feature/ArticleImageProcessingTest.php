<?php

use App\Models\Article;
use App\Support\Images\ContentImageProcessor;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->disk = Storage::disk('public');
    $this->processor = app(ContentImageProcessor::class);
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
    $this->disk->put('content/tmp/photo-tmpabc123.jpg', fakeJpegImage(1200, 800));

    $article = Article::factory()->create([
        'content' => [imageBlock('content/tmp/photo-tmpabc123.jpg')],
    ]);

    $converted = $this->processor->process($article);

    $article->refresh();

    expect($converted)->toBeTrue()
        ->and($article->content[0]['data']['image'])->toBe("articles/{$article->id}/photo.webp")
        ->and($this->disk->exists("articles/{$article->id}/photo.webp"))->toBeTrue()
        ->and($this->disk->exists("articles/{$article->id}/photo-desktop.webp"))->toBeTrue()
        ->and($this->disk->exists("articles/{$article->id}/photo-mobile.webp"))->toBeTrue()
        ->and($this->disk->exists("articles/{$article->id}/photo-thumbnail.webp"))->toBeTrue()
        ->and($this->disk->exists('content/tmp/photo-tmpabc123.jpg'))->toBeFalse();
});

test('rich text blocks are preserved when image blocks are processed', function () {
    $this->disk->put('content/tmp/photo-tmpabc123.jpg', fakeJpegImage(600, 400));

    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => '<p>Hello</p>']],
            imageBlock('content/tmp/photo-tmpabc123.jpg'),
        ],
    ]);

    $this->processor->process($article);

    $article->refresh();

    expect($article->content[0])->toBe(['type' => 'richText', 'data' => ['content' => '<p>Hello</p>']])
        ->and($article->content[1]['data']['image'])->toBe("articles/{$article->id}/photo.webp")
        ->and($article->content[1]['data']['alt'])->toBe('Alt text');
});

test('faq blocks are preserved when image blocks are processed', function () {
    $this->disk->put('content/tmp/photo-tmpabc123.jpg', fakeJpegImage(600, 400));

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
            imageBlock('content/tmp/photo-tmpabc123.jpg'),
        ],
    ]);

    $this->processor->process($article);

    $article->refresh();

    expect($article->content[0])->toBe($faqBlock)
        ->and($article->content[1]['data']['image'])->toBe("articles/{$article->id}/photo.webp");
});

test('processing is idempotent for already converted blocks', function () {
    $this->disk->put('content/tmp/photo-tmpabc123.jpg', fakeJpegImage(600, 400));

    $article = Article::factory()->create([
        'content' => [imageBlock('content/tmp/photo-tmpabc123.jpg')],
    ]);

    $this->processor->process($article);

    $contentAfterFirstRun = $article->refresh()->content;

    $secondRunConverted = $this->processor->process($article);

    expect($secondRunConverted)->toBeFalse()
        ->and($article->refresh()->content)->toBe($contentAfterFirstRun)
        ->and($this->disk->exists("articles/{$article->id}/photo.webp"))->toBeTrue();
});

test('a name collision within the article folder gets a numeric suffix', function () {
    $this->disk->put('content/tmp/photo-tmpaaa111.jpg', fakeJpegImage(600, 400));
    $this->disk->put('content/tmp/photo-tmpbbb222.jpg', fakeJpegImage(500, 300));

    $article = Article::factory()->create([
        'content' => [
            imageBlock('content/tmp/photo-tmpaaa111.jpg'),
            imageBlock('content/tmp/photo-tmpbbb222.jpg'),
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
    $this->disk->put('content/tmp/new-tmpccc333.jpg', fakeJpegImage(600, 400));

    $article = Article::factory()->create([
        'content' => [imageBlock('content/tmp/new-tmpccc333.jpg')],
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
    $this->disk->put('content/tmp/bad-tmpddd444.jpg', 'not-an-image');

    $article = Article::factory()->create([
        'content' => [imageBlock('content/tmp/bad-tmpddd444.jpg')],
    ]);

    $converted = $this->processor->process($article);

    $article->refresh();

    expect($converted)->toBeFalse()
        ->and($article->content[0]['data']['image'])->toBe('content/tmp/bad-tmpddd444.jpg')
        ->and($this->disk->exists('content/tmp/bad-tmpddd444.jpg'))->toBeTrue();
});

test('deleting an article removes its image directory', function () {
    $article = Article::factory()->create();

    $this->disk->put("articles/{$article->id}/photo.webp", 'image');

    $article->delete();

    expect($this->disk->exists("articles/{$article->id}/photo.webp"))->toBeFalse()
        ->and($this->disk->directories('articles'))->not->toContain("articles/{$article->id}");
});

test('a featured tmp upload is converted into the featured subdirectory', function () {
    $this->disk->put('content/tmp/hero-tmpeee555.jpg', fakeJpegImage(1200, 800));

    $article = Article::factory()->create([
        'featured_image' => 'content/tmp/hero-tmpeee555.jpg',
        'featured_image_alt' => 'A hero',
    ]);

    $converted = $this->processor->process($article);

    $article->refresh();

    expect($converted)->toBeTrue()
        ->and($article->featured_image)->toBe("articles/{$article->id}/featured/hero.webp")
        ->and($this->disk->exists("articles/{$article->id}/featured/hero.webp"))->toBeTrue()
        ->and($this->disk->exists("articles/{$article->id}/featured/hero-desktop.webp"))->toBeTrue()
        ->and($this->disk->exists("articles/{$article->id}/featured/hero-mobile.webp"))->toBeTrue()
        ->and($this->disk->exists("articles/{$article->id}/featured/hero-thumbnail.webp"))->toBeTrue()
        ->and($this->disk->exists('content/tmp/hero-tmpeee555.jpg'))->toBeFalse();
});

test('processing is idempotent for an already converted featured image', function () {
    $this->disk->put('content/tmp/hero-tmpeee555.jpg', fakeJpegImage(600, 400));

    $article = Article::factory()->create([
        'featured_image' => 'content/tmp/hero-tmpeee555.jpg',
    ]);

    $this->processor->process($article);

    $pathAfterFirstRun = $article->refresh()->featured_image;

    expect($this->processor->process($article))->toBeFalse()
        ->and($article->refresh()->featured_image)->toBe($pathAfterFirstRun)
        ->and($this->disk->exists($pathAfterFirstRun))->toBeTrue();
});

test('replacing the featured image removes the previous variant set', function () {
    $this->disk->put('content/tmp/new-hero-tmpfff666.jpg', fakeJpegImage(600, 400));

    $article = Article::factory()->create([
        'featured_image' => 'content/tmp/new-hero-tmpfff666.jpg',
    ]);

    foreach (['old-hero.webp', 'old-hero-desktop.webp', 'old-hero-mobile.webp', 'old-hero-thumbnail.webp'] as $file) {
        $this->disk->put("articles/{$article->id}/featured/{$file}", 'stale');
    }

    $this->processor->process($article);

    expect($this->disk->exists("articles/{$article->id}/featured/new-hero.webp"))->toBeTrue()
        ->and($this->disk->exists("articles/{$article->id}/featured/old-hero.webp"))->toBeFalse()
        ->and($this->disk->exists("articles/{$article->id}/featured/old-hero-desktop.webp"))->toBeFalse();
});

test('clearing the featured image empties the featured subdirectory', function () {
    $article = Article::factory()->create(['featured_image' => null]);

    foreach (['hero.webp', 'hero-desktop.webp', 'hero-mobile.webp', 'hero-thumbnail.webp'] as $file) {
        $this->disk->put("articles/{$article->id}/featured/{$file}", 'stale');
    }

    $this->processor->process($article);

    expect($this->disk->files("articles/{$article->id}/featured"))->toBe([]);
});

test('content images and the featured image do not clean each other up', function () {
    $this->disk->put('content/tmp/photo-tmpaaa111.jpg', fakeJpegImage(600, 400));
    $this->disk->put('content/tmp/photo-tmpbbb222.jpg', fakeJpegImage(500, 300));

    $article = Article::factory()->create([
        'content' => [imageBlock('content/tmp/photo-tmpaaa111.jpg')],
        'featured_image' => 'content/tmp/photo-tmpbbb222.jpg',
    ]);

    $converted = $this->processor->process($article);

    $article->refresh();

    // Separate directories, so the shared base name needs no numeric suffix and
    // neither cleanup pass can see — let alone delete — the other's files.
    expect($converted)->toBeTrue()
        ->and($article->content[0]['data']['image'])->toBe("articles/{$article->id}/photo.webp")
        ->and($article->featured_image)->toBe("articles/{$article->id}/featured/photo.webp")
        ->and($this->disk->exists("articles/{$article->id}/photo.webp"))->toBeTrue()
        ->and($this->disk->exists("articles/{$article->id}/photo-thumbnail.webp"))->toBeTrue()
        ->and($this->disk->exists("articles/{$article->id}/featured/photo.webp"))->toBeTrue()
        ->and($this->disk->exists("articles/{$article->id}/featured/photo-thumbnail.webp"))->toBeTrue();
});

test('a corrupt featured upload is left untouched and does not break processing', function () {
    $this->disk->put('content/tmp/bad-tmpggg777.jpg', 'not-an-image');

    $article = Article::factory()->create([
        'featured_image' => 'content/tmp/bad-tmpggg777.jpg',
    ]);

    $converted = $this->processor->process($article);

    $article->refresh();

    expect($converted)->toBeFalse()
        ->and($article->featured_image)->toBe('content/tmp/bad-tmpggg777.jpg')
        ->and($this->disk->exists('content/tmp/bad-tmpggg777.jpg'))->toBeTrue();
});

test('deleting an article removes its featured image directory', function () {
    $article = Article::factory()->create();

    $this->disk->put("articles/{$article->id}/featured/hero.webp", 'image');

    $article->delete();

    expect($this->disk->exists("articles/{$article->id}/featured/hero.webp"))->toBeFalse();
});
