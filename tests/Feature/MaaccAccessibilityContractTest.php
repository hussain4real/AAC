<?php

/** @return array{0: int, 1: int, 2: int} */
function maaccRgb(string $hex): array
{
    $hex = ltrim($hex, '#');

    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

function maaccContrast(string $foreground, string $background): float
{
    $luminance = static function (string $hex): float {
        $channels = array_map(static function (int $channel): float {
            $normalized = $channel / 255;

            return $normalized <= 0.04045
                ? $normalized / 12.92
                : (($normalized + 0.055) / 1.055) ** 2.4;
        }, maaccRgb($hex));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    };

    $first = $luminance($foreground);
    $second = $luminance($background);

    return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
}

test('shared MAACC color tokens meet AA contrast for normal text', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $pairs = [
        ['#626b7d', '#ffffff'],
        ['#626b7d', '#eef0f5'],
        ['#087260', '#d9f2ec'],
        ['#a93600', '#ffe7da'],
        ['#855300', '#fcefcf'],
        ['#b42318', '#fbe2e0'],
        ['#a6b1c3', '#0f1b30'],
        ['#4fd0bd', '#0f2d29'],
        ['#ff9a6b', '#3a2417'],
        ['#ecb44e', '#332815'],
    ];

    foreach ($pairs as [$foreground, $background]) {
        expect($css)->toContain($foreground, $background)
            ->and(maaccContrast($foreground, $background))->toBeGreaterThanOrEqual(4.5);
    }
});

test('shared MAACC primitives expose responsive and keyboard contracts', function () {
    $layout = file_get_contents(resource_path('js/layouts/maacc-layout.tsx'));
    $primitives = file_get_contents(resource_path('js/components/maacc/ui.tsx'));
    $styles = file_get_contents(resource_path('css/app.css'));

    expect($layout)->toContain('Skip to main content', 'id="maacc-main-content"')
        ->and($primitives)->toContain('role="tablist"', 'role="tab"', 'role="switch"', 'aria-checked={on}')
        ->and($styles)->toContain('height: 100dvh', '@media (max-width: 767px)', '@media (prefers-reduced-motion: reduce)');
});
