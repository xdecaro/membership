<?php

declare(strict_types=1);
defined('_JEXEC') || define('_JEXEC', 1);

$root = dirname(__DIR__);
$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$version = trim((string) file_get_contents($root . '/VERSION'));
$expect(version_compare($version, '1.6.1', '>='), 'VERSION must be 1.6.1 or newer.');

foreach ([
    'component/media/css/admin.css',
    'component/media/css/core-bridge.css',
    'component/media/js/admin.js',
    'component/media/joomla.asset.json',
    'component/admin/src/Service/AdminAssetService.php',
    'component/admin/sql/updates/mysql/1.6.1.sql',
] as $path) {
    $expect(is_file($root . '/' . $path), "Missing required Membership asset/runtime file: {$path}");
}

$service = (string) file_get_contents($root . '/component/admin/src/Service/AdminAssetService.php');
foreach ([
    'addExtensionRegistryFile',
    "useStyle('com_decaromembership.admin')",
    "useScript('com_decaromembership.admin')",
] as $marker) {
    $expect(str_contains($service, $marker), "AdminAssetService missing {$marker}.");
}

foreach ([
    'component/admin/src/View/Dashboard/HtmlView.php',
    'component/admin/src/View/Records/HtmlView.php',
    'component/admin/src/View/Record/HtmlView.php',
    'component/admin/src/View/Information/HtmlView.php',
] as $path) {
    $source = (string) file_get_contents($root . '/' . $path);
    $expect(str_contains($source, 'AdminAssetService::useAssets'), "{$path} must use AdminAssetService.");
}

foreach ([
    'component/admin/tmpl/dashboard/default.php',
    'component/admin/tmpl/records/default.php',
    'component/admin/tmpl/record/default.php',
    'component/admin/tmpl/information/default.php',
    'component/admin/tmpl/information/core.php',
] as $path) {
    $source = (string) file_get_contents($root . '/' . $path);
    $expect(!str_contains($source, '<link rel="stylesheet"'), "{$path} still injects CSS manually.");
    $expect(!str_contains($source, '<script src='), "{$path} still injects JS manually.");
}

$installer = (string) file_get_contents($root . '/package/script.php');
foreach ([
    "media/com_decaromembership/css/admin.css",
    "media/com_decaromembership/js/admin.js",
    "Membership media assets are missing after installation",
] as $marker) {
    $expect(str_contains($installer, $marker), "Installer media verification missing {$marker}.");
}

$css = (string) file_get_contents($root . '/component/media/css/admin.css');
$expect(str_contains($css, '.dm-grid-kpi'), 'Membership dashboard grid CSS is missing.');
$expect(str_contains($css, '.dm-card'), 'Membership card CSS is missing.');
$expect(str_contains($css, '@media(max-width:700px)'), 'Membership mobile CSS is missing.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.6.1+ admin asset compatibility contract OK\n";
