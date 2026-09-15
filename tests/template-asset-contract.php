<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$templates = [
    'dashboard'        => ['component/admin/tmpl/dashboard/default.php', false],
    'information'      => ['component/admin/tmpl/information/default.php', false],
    'information-core' => ['component/admin/tmpl/information/core.php', false],
    'record'           => ['component/admin/tmpl/record/default.php', true],
    'records'          => ['component/admin/tmpl/records/default.php', true],
];

$errors = [];

foreach ($templates as $name => [$relativePath, $needsScript]) {
    $path = $root . '/' . $relativePath;
    $code = is_file($path) ? (string) file_get_contents($path) : '';

    if ($code === '') {
        $errors[] = "$name template is missing";
        continue;
    }

    if (!str_contains($code, 'getWebAssetManager()')) {
        $errors[] = "$name template must obtain the Joomla WebAssetManager";
    }

    if (!str_contains($code, 'registerAndUseStyle') || !str_contains($code, 'com_decaromembership/css/admin.css')) {
        $errors[] = "$name template must register and use Membership admin.css directly";
    }

    if ($needsScript && (!str_contains($code, 'registerAndUseScript') || !str_contains($code, 'com_decaromembership/js/admin.js'))) {
        $errors[] = "$name template must register and use Membership admin.js directly";
    }
}

$coreInfo = (string) file_get_contents($root . '/component/admin/tmpl/information/core.php');
if (str_contains($coreInfo, '<dd>1.1.0</dd>')) {
    $errors[] = 'Core information layout must not hard-code the old Membership 1.1.0 version';
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Template asset contract OK\n");
