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
$expect(version_compare($version, '1.9.10', '>='), 'VERSION must be 1.9.10 or newer.');

$config = (string) file_get_contents($root . '/component/admin/src/Config/CaseEntities.php');
$model = (string) file_get_contents($root . '/component/admin/src/Model/RecordModel.php');
$runtime = (string) file_get_contents($root . '/tests/member-lifecycle-runtime.php');
$it = (string) file_get_contents($root . '/component/admin/language/it-IT/com_decaromembership.ini');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.10.sql');

foreach ([
    "'status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_RENEWAL_STATUS','type'=>'select','required'=>true,'default'=>'due'",
    "'published'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PUBLISHED','type'=>'published','default'=>1]",
] as $marker) {
    $expect(str_contains($config, $marker), "Renewal config marker missing {$marker}.");
}

$expect(str_contains($it, 'COM_DECAROMEMBERSHIP_FIELD_RENEWAL_STATUS="Stato rinnovo"'), 'Italian renewal-status label missing.');
$memberStart = strpos($model, "if (\$entity === 'members') {");
$memberEnd = strpos(
    $model,
    "\n        \$query = \$db->getQuery(true)\n            ->select([\$db->quoteName('id'), \$db->quoteName(\$config['title_field'], 'title')])",
    $memberStart === false ? 0 : $memberStart
);
$memberBlock = ($memberStart !== false && $memberEnd !== false)
    ? substr($model, $memberStart, $memberEnd - $memberStart)
    : '';
$expect($memberBlock !== '', 'Member relation block could not be isolated.');
$expect(!str_contains(
    $memberBlock,
    "\$query->where(\$db->quoteName('published') . ' = 1');"
), 'Administrator member relations must not hide unpublished members.');

foreach ([
    'Unpublished member disappeared from administrator relation options.',
    'Renewal did not default to due.',
] as $marker) {
    $expect(str_contains($runtime, $marker), "Runtime renewal regression missing {$marker}.");
}

$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b/i', $update), '1.9.10 migration marker must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.10 renewal form contract OK\n";
