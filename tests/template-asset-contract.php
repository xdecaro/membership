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

    if (!str_contains($code, '/media/com_decaromembership/css/admin.css')) {
        $errors[] = "$name template must render the external Membership admin.css link directly";
    }

    if ($needsScript && !str_contains($code, '/media/com_decaromembership/js/admin.js')) {
        $errors[] = "$name template must render the external Membership admin.js script directly";
    }

    if (preg_match('/<style\b/i', $code)) {
        $errors[] = "$name template must not introduce inline CSS";
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

fwrite(STDOUT, "Template direct asset contract OK\n");
