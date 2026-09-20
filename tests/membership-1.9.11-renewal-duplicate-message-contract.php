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
$expect(version_compare($version, '1.9.11', '>='), 'VERSION must be 1.9.11 or newer.');

$model = (string) file_get_contents($root . '/component/admin/src/Model/RecordModel.php');
$repository = (string) file_get_contents($root . '/component/admin/src/Service/RecordRepository.php');
$it = (string) file_get_contents($root . '/component/admin/language/it-IT/com_decaromembership.ini');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.11.sql');

foreach ([
    'renewalDuplicateExists(',
    'COM_DECAROMEMBERSHIP_ERROR_RENEWAL_DUPLICATE',
] as $marker) {
    $expect(str_contains($model, $marker), "RecordModel duplicate-renewal marker missing {$marker}.");
}

foreach ([
    'public function renewalDuplicateExists',
    '#__decaromembership_renewals',
    'association_year',
] as $marker) {
    $expect(str_contains($repository, $marker), "RecordRepository duplicate-renewal marker missing {$marker}.");
}

$expect(
    str_contains($it, 'COM_DECAROMEMBERSHIP_ERROR_RENEWAL_DUPLICATE="Esiste già un rinnovo per questo socio e anno associativo."'),
    'Italian duplicate-renewal message missing.'
);

$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b/i', $update), '1.9.11 migration marker must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.11 duplicate renewal message contract OK\n";
