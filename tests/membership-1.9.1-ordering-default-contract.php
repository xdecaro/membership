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
$expect($version === '1.9.1', 'VERSION must be 1.9.1.');

$memberConfig = (string) file_get_contents($root . '/component/admin/src/Config/MemberCoreEntities.php');
$caseConfig = (string) file_get_contents($root . '/component/admin/src/Config/CaseEntities.php');
$operationsConfig = (string) file_get_contents($root . '/component/admin/src/Config/OperationsEntities.php');
$install = str_replace(chr(96), '', (string) file_get_contents($root . '/component/admin/sql/install.mysql.utf8mb4.sql'));
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.1.sql');

$orderingDefault = "'ordering'=>['label'=>'JFIELD_ORDERING_LABEL','type'=>'number','default'=>0]";
$expect(str_contains($memberConfig, $orderingDefault), 'Categories ordering must default to 0.');
$expect(str_contains($caseConfig, $orderingDefault), 'Case statuses ordering must default to 0.');
$expect(str_contains($operationsConfig, $orderingDefault), 'Checklist templates ordering must default to 0.');

foreach ([
    '#__decaromembership_categories',
    '#__decaromembership_case_statuses',
    '#__decaromembership_checklist_templates',
] as $table) {
    $tablePos = strpos($install, $table);
    $expect($tablePos !== false, "Clean schema missing {$table}.");
    if ($tablePos !== false) {
        $segment = substr($install, $tablePos, 900);
        $expect(str_contains($segment, 'ordering INT NOT NULL DEFAULT 0'), "{$table} ordering must remain NOT NULL DEFAULT 0.");
    }
}

$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i', $update), '1.9.1 migration marker must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.1 ordering defaults contract OK\n";
