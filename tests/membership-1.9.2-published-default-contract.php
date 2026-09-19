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
$expect($version === '1.9.2', 'VERSION must be 1.9.2.');

$configFiles = [
    'component/admin/src/Config/MemberCoreEntities.php',
    'component/admin/src/Config/CaseEntities.php',
    'component/admin/src/Config/OperationsEntities.php',
    'component/admin/src/Config/OrganizationEntities.php',
    'component/admin/src/Config/FinanceEntities.php',
];

$publishedMarker = "'published'=>['label'=>'JSTATUS','type'=>'published','default'=>1]";
$totalPublishedDefaults = 0;
foreach ($configFiles as $relativePath) {
    $content = (string) file_get_contents($root . '/' . $relativePath);
    $count = substr_count($content, $publishedMarker);
    $expect($count > 0, "{$relativePath} must explicitly default published records to 1.");
    $totalPublishedDefaults += $count;
}
$expect($totalPublishedDefaults === 13, 'All 13 publishable Membership entity definitions must default to published=1.');

$template = (string) file_get_contents($root . '/component/admin/tmpl/records/default.php');
foreach (["'JPUBLISHED'", "'JUNPUBLISHED'", "'type']??'')==='published'"] as $marker) {
    $expect(str_contains($template, $marker), "Records list missing readable published-state marker {$marker}.");
}

$install = str_replace(chr(96), '', (string) file_get_contents($root . '/component/admin/sql/install.mysql.utf8mb4.sql'));
$expect(substr_count($install, 'published TINYINT NOT NULL DEFAULT 1') >= 10, 'Clean schema must keep published columns at DEFAULT 1.');

$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.2.sql');
$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i', $update), '1.9.2 migration marker must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.2 published defaults contract OK\n";
