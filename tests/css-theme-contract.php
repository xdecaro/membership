<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$cssPath = $root . '/component/media/css/admin.css';
$css = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';

$errors = [];

if ($css === '') {
    $errors[] = 'Membership admin.css is missing';
} else {
    if (str_contains($css, 'prefers-color-scheme:dark') || str_contains($css, 'prefers-color-scheme: dark')) {
        $errors[] = 'Membership admin.css must not force dark colors from the operating-system color scheme';
    }

    foreach (['--body-bg', '--body-color', '--border-color'] as $joomlaVariable) {
        if (!str_contains($css, $joomlaVariable)) {
            $errors[] = "Membership admin.css must inherit Joomla theme variable {$joomlaVariable}";
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "CSS theme contract OK\n");
