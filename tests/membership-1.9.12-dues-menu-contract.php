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
$expect(version_compare($version, '1.9.12', '>='), 'VERSION must be 1.9.12 or newer.');

$manifest = (string) file_get_contents($root . '/component/decaromembership.xml');
$finance = (string) file_get_contents($root . '/component/admin/src/Config/FinanceEntities.php');
$itSys = (string) file_get_contents($root . '/component/admin/language/it-IT/com_decaromembership.sys.ini');
$it = (string) file_get_contents($root . '/component/admin/language/it-IT/com_decaromembership.ini');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.12.sql');

$expect(
    str_contains($manifest, 'view=records&amp;entity=dues">COM_DECAROMEMBERSHIP_DUES</menu>'),
    'Dues submenu entry is missing from the component manifest.'
);

foreach ([
    "'amount'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_AMOUNT','type'=>'money','required'=>true]",
    "'status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_DUE_STATUS','type'=>'select','required'=>true,'default'=>'unpaid'",
    "'published'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PUBLISHED','type'=>'published','default'=>1]",
] as $marker) {
    $expect(str_contains($finance, $marker), "Dues config marker missing {$marker}.");
}

$expect(str_contains($itSys, 'COM_DECAROMEMBERSHIP_DUES="Quote"'), 'Italian Dues submenu label missing.');
$expect(str_contains($it, 'COM_DECAROMEMBERSHIP_FIELD_DUE_STATUS="Stato quota"'), 'Italian Dues status label missing.');
$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b/i', $update), '1.9.12 migration marker must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.12 dues menu contract OK\n";
