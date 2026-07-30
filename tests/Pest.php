<?php

use App\Models\Setting;
use App\Support\Site\SitePageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Store the header navigation links the settings page would have written.
 *
 * @param  list<array{type?: string, category_id?: int|null, label?: string|null}>  $links
 */
function storeHeaderLinks(array $links): void
{
    Setting::setValue(SitePageSettings::SETTINGS_KEY, ['navigation' => ['header' => $links]]);
}

/**
 * Generate a real JPEG binary of the given dimensions for image pipeline tests.
 */
function fakeJpegImage(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);

    ob_start();
    imagejpeg($image);

    return (string) ob_get_clean();
}
