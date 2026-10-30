<?php

/*
 * The brand palette (sage), read from resources/palette.json, the one source shared with tailwind.config.js and
 * resources/css/palette.css. For the places CSS variables cannot reach: emails, PDFs (dompdf), chart colours handed to
 * <canvas>, server-drawn SVG and the theme-color meta tag. 'rgb' holds "r, g, b" for rgba().
 */
$palette = json_decode((string) file_get_contents(resource_path('palette.json')), true);

$rgb = static fn (string $hex): string => implode(', ', array_map('hexdec', str_split(ltrim($hex, '#'), 2)));

return [
    'sage' => $palette['sage'],
    'ink' => $palette['ink'],
    'muted' => $palette['muted'],
    'rgb' => array_map($rgb, $palette['sage']) + ['ink' => $rgb($palette['ink'])],
];
