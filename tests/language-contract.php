<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$scanRoots = [
    $root . '/component/admin/src',
    $root . '/component/admin/tmpl',
    $root . '/component/admin/config.xml',
];

$used = [];
$collect = static function (string $path) use (&$used): void {
    $content = (string) file_get_contents($path);
    preg_match_all('/COM_DECAROMEMBERSHIP_[A-Z0-9_]+/', $content, $matches);
    foreach ($matches[0] as $key) {
        $used[$key] = true;
    }
};

foreach ($scanRoots as $scanRoot) {
    if (is_file($scanRoot)) {
        $collect($scanRoot);
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanRoot));
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $extension = strtolower($file->getExtension());
        if (!in_array($extension, ['php', 'xml'], true)) {
            continue;
        }
        $collect($file->getPathname());
    }
}

ksort($used);
$errors = [];

foreach (['it-IT', 'en-GB', 'fr-FR'] as $locale) {
    $defined = [];
    foreach (['com_decaromembership.ini', 'com_decaromembership.sys.ini'] as $file) {
        $path = $root . '/component/admin/language/' . $locale . '/' . $file;
        $content = is_file($path) ? (string) file_get_contents($path) : '';
        preg_match_all('/^(COM_DECAROMEMBERSHIP(?:_[A-Z0-9_]+)?)=/m', $content, $matches);
        foreach ($matches[1] as $key) {
            $defined[$key] = true;
        }
    }

    $missing = array_values(array_diff(array_keys($used), array_keys($defined)));
    if ($missing !== []) {
        $errors[] = $locale . ' missing: ' . implode(', ', $missing);
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Membership language contract OK\n");
