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
$expect(version_compare($version, '1.6.2', '>='), 'VERSION must be 1.6.2 or newer.');

$service = (string) file_get_contents($root . '/component/admin/src/Service/AdminAssetService.php');
$expect(str_contains($service, 'addStyleSheet('), 'Current Membership runtime must emit its stylesheet through HtmlDocument.');
$expect(str_contains($service, 'addScript('), 'Current Membership runtime must emit its script through HtmlDocument.');

$assetManifest = json_decode((string) file_get_contents($root . '/component/media/joomla.asset.json'), true);
$expect(($assetManifest['version'] ?? '') === $version, 'joomla.asset.json version must match VERSION.');

$names = [];
foreach (($assetManifest['assets'] ?? []) as $asset) {
    $names[] = ($asset['type'] ?? '') . ':' . ($asset['name'] ?? '');
}
$expect(in_array('style:com_decaromembership.admin', $names, true), 'Membership style asset missing from registry.');
$expect(in_array('script:com_decaromembership.admin', $names, true), 'Membership script asset missing from registry.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.6.2+ asset compatibility contract OK\n";
