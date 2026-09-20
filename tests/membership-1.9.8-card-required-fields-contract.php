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
$expect($version === '1.9.8', 'VERSION must be 1.9.8.');

$config = (string) file_get_contents($root . '/component/admin/src/Config/CaseEntities.php');
$runtime = (string) file_get_contents($root . '/tests/member-lifecycle-runtime.php');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.8.sql');

foreach ([
    "'type'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CARD_TYPE','type'=>'select','required'=>true",
    "'status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CARD_STATUS','type'=>'select','required'=>true",
] as $marker) {
    $expect(str_contains($config, $marker), "Required card field marker missing {$marker}.");
}

foreach ([
    'Card creation without type must be rejected.',
    'Card creation without status must be rejected.',
] as $marker) {
    $expect(str_contains($runtime, $marker), "Runtime required-card regression missing {$marker}.");
}

$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b/i', $update), '1.9.8 migration marker must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.8 card required fields contract OK\n";
