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
$expect(version_compare($version, '1.6.3', '>='), 'VERSION must be 1.6.3 or newer.');

$service = (string) file_get_contents($root . '/component/admin/src/Service/AdminAssetService.php');
foreach ([
    "private const VERSION = '",
    "Uri::root(true)",
    "addStyleSheet(",
    "/media/com_decaromembership",
    "/css/admin.css",
    "addScript(",
    "/js/admin.js",
] as $marker) {
    $expect(str_contains($service, $marker), "AdminAssetService missing direct-delivery marker: {$marker}");
}

$dashboard = (string) file_get_contents($root . '/component/admin/tmpl/dashboard/default.php');
$expect(!preg_match('/^\s*">\s*$/m', $dashboard), 'Dashboard must not contain the stray quote/angle-bracket output.');

$css = (string) file_get_contents($root . '/component/media/css/admin.css');
foreach (['.dm-grid-kpi', '.dm-card'] as $selector) {
    $expect(str_contains($css, $selector), "Membership CSS missing {$selector}.");
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.6.3+ direct administrator asset compatibility contract OK\n";
