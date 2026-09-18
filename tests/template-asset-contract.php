<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$templates = [
    'dashboard'        => 'component/admin/tmpl/dashboard/default.php',
    'information'      => 'component/admin/tmpl/information/default.php',
    'information-core' => 'component/admin/tmpl/information/core.php',
    'record'           => 'component/admin/tmpl/record/default.php',
    'records'          => 'component/admin/tmpl/records/default.php',
];

$errors = [];

foreach ($templates as $name => $relativePath) {
    $path = $root . '/' . $relativePath;
    $code = is_file($path) ? (string) file_get_contents($path) : '';

    if ($code === '') {
        $errors[] = "$name template is missing";
        continue;
    }

    if (str_contains($code, '<link rel="stylesheet"') || str_contains($code, '<script src=')) {
        $errors[] = "$name template must not inject Membership CSS/JS manually";
    }

    if (preg_match('/<style\\b/i', $code)) {
        $errors[] = "$name template must not introduce inline CSS";
    }
}

$assetService = (string) file_get_contents($root . '/component/admin/src/Service/AdminAssetService.php');
foreach (["getRegistry()->addExtensionRegistryFile", "useStyle('com_decaromembership.admin')", "useScript('com_decaromembership.admin')"] as $marker) {
    if (!str_contains($assetService, $marker)) {
        $errors[] = "AdminAssetService missing {$marker}";
    }
}

$assetManifest = (string) file_get_contents($root . '/component/media/joomla.asset.json');
foreach (['com_decaromembership/css/admin.css', 'com_decaromembership/js/admin.js'] as $asset) {
    if (!str_contains($assetManifest, $asset)) {
        $errors[] = "joomla.asset.json missing {$asset}";
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

fwrite(STDOUT, "Template centralized asset contract OK\n");
