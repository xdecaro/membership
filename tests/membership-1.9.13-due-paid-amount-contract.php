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
$expect($version === '1.9.13', 'VERSION must be 1.9.13.');

$model = (string) file_get_contents($root . '/component/admin/src/Model/RecordModel.php');
$runtime = (string) file_get_contents($root . '/tests/member-lifecycle-runtime.php');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.13.sql');

foreach ([
    '$entity === \'dues\'',
    '($data[\'paid_amount\'] ?? null) === null',
    '$data[\'paid_amount\'] = 0.0',
] as $marker) {
    $expect(str_contains($model, $marker), "Due paid amount normalization marker missing {$marker}.");
}

foreach ([
    "'paid_amount' => ''",
    'Blank due paid amount did not persist as zero.',
    'Due amount was not persisted.',
] as $marker) {
    $expect(str_contains($runtime, $marker), "Runtime due regression missing {$marker}.");
}

$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b/i', $update), '1.9.13 migration marker must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.13 due paid amount contract OK\n";
