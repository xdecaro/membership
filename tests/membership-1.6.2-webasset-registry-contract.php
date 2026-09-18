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
$expect($version === '1.6.2', 'VERSION must be 1.6.2.');

$service = (string) file_get_contents($root . '/component/admin/src/Service/AdminAssetService.php');
foreach ([
    "private const COMPONENT = 'com_decaromembership'",
    "getRegistry()->addExtensionRegistryFile(self::COMPONENT)",
    "useStyle('com_decaromembership.admin')",
    "useScript('com_decaromembership.admin')",
] as $marker) {
    $expect(str_contains($service, $marker), "AdminAssetService missing registry marker: {$marker}");
}

$expect(!str_contains($service, 'registerAndUseStyle'), '1.6.2 must not ad-hoc register the Membership style.');
$expect(!str_contains($service, 'registerAndUseScript'), '1.6.2 must not ad-hoc register the Membership script.');

$assetManifest = json_decode((string) file_get_contents($root . '/component/media/joomla.asset.json'), true);
$expect(($assetManifest['version'] ?? '') === '1.6.2', 'joomla.asset.json version must be 1.6.2.');

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

echo "Membership 1.6.2 Web Asset registry contract OK\n";
