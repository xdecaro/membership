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
$expect($version === '1.9.3', 'VERSION must be 1.9.3.');

$config = (string) file_get_contents($root . '/component/admin/src/Config/MemberCoreEntities.php');
$lifecycle = (string) file_get_contents($root . '/component/admin/src/Service/MemberLifecycleService.php');
$runtime = (string) file_get_contents($root . '/tests/member-lifecycle-runtime.php');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.3.sql');

$expect(str_contains(
    $config,
    "'published'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PUBLISHED','type'=>'published','default'=>1]"
), 'Members must explicitly default to published=1.');

foreach ([
    '$legacyBootstrap = $id > 0 && $oldStatus === \'\';',
    'if (!$legacyBootstrap && ($id < 1 || $status !== $oldStatus))',
    '$becameActive = !$legacyBootstrap',
    'if (!$legacyBootstrap && $status !== $oldStatus',
] as $marker) {
    $expect(str_contains($lifecycle, $marker), "Legacy lifecycle guard missing {$marker}.");
}

foreach ([
    'CI Legacy Blank Status',
    'Legacy blank-status bootstrap invented lifecycle date',
    'New member did not default to published=1.',
] as $marker) {
    $expect(str_contains($runtime, $marker), "Runtime regression missing {$marker}.");
}

$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i', $update), '1.9.3 migration marker must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.3 legacy lifecycle contract OK\n";
