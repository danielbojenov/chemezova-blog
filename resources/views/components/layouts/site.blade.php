@props([
    'title' => null,
    'description' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ? $title.' — '.config('app.name') : config('app.name') }}</title>

        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-canvas font-sans text-body antialiased">
        <x-site.header />

        <main class="flex-1">
            {{ $slot }}
        </main>

        <x-site.footer />
    </body>
</html>
