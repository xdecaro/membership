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
$expect(version_compare($version, '1.9.2', '>='), 'VERSION must be 1.9.2 or newer.');

$configFiles = [
    'component/admin/src/Config/MemberCoreEntities.php',
    'component/admin/src/Config/CaseEntities.php',
    'component/admin/src/Config/OperationsEntities.php',
    'component/admin/src/Config/OrganizationEntities.php',
    'component/admin/src/Config/FinanceEntities.php',
];

$totalPublishedFields = 0;
$totalPublishedDefaults = 0;
foreach ($configFiles as $relativePath) {
    $content = (string) file_get_contents($root . '/' . $relativePath);
    preg_match_all("/'published'=>\\[[^\\]]*'type'=>'published'[^\\]]*\\]/", $content, $publishedFields);
    preg_match_all("/'published'=>\\[[^\\]]*'type'=>'published'[^\\]]*'default'=>1[^\\]]*\\]/", $content, $publishedDefaults);
    $countFields = count($publishedFields[0]);
    $countDefaults = count($publishedDefaults[0]);
    $expect($countFields > 0, "{$relativePath} must contain publishable entities.");
    $expect($countDefaults === $countFields, "{$relativePath} must explicitly default every published field to 1.");
    $totalPublishedFields += $countFields;
    $totalPublishedDefaults += $countDefaults;
}
$expect($totalPublishedDefaults === $totalPublishedFields, 'All publishable Membership entity definitions must default to published=1.');

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
