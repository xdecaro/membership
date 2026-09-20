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
$expect(version_compare($version, '1.9.7', '>='), 'VERSION must be 1.9.7 or newer.');

$config = (string) file_get_contents($root . '/component/admin/src/Config/CaseEntities.php');
$recordTemplate = (string) file_get_contents($root . '/component/admin/tmpl/record/default.php');
$listTemplate = (string) file_get_contents($root . '/component/admin/tmpl/records/default.php');
$it = (string) file_get_contents($root . '/component/admin/language/it-IT/com_decaromembership.ini');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.7.sql');

foreach ([
    "'status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CARD_STATUS'",
    "'pending'=>'COM_DECAROMEMBERSHIP_CARD_STATUS_PENDING'",
    "'active'=>'COM_DECAROMEMBERSHIP_CARD_STATUS_ACTIVE'",
    "'expired'=>'COM_DECAROMEMBERSHIP_CARD_STATUS_EXPIRED'",
    "'lost'=>'COM_DECAROMEMBERSHIP_CARD_STATUS_LOST'",
    "'revoked'=>'COM_DECAROMEMBERSHIP_CARD_STATUS_REVOKED'",
    "'replaced'=>'COM_DECAROMEMBERSHIP_CARD_STATUS_REPLACED'",
    "'published'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PUBLISHED','type'=>'published','default'=>1]",
] as $marker) {
    $expect(str_contains($config, $marker), "Card config label marker missing {$marker}.");
}

foreach ([
    'COM_DECAROMEMBERSHIP_FIELD_CARD_STATUS="Stato tessera"',
    'COM_DECAROMEMBERSHIP_CARD_STATUS_PENDING="In attesa"',
    'COM_DECAROMEMBERSHIP_CARD_STATUS_ACTIVE="Attiva"',
    'COM_DECAROMEMBERSHIP_CARD_STATUS_EXPIRED="Scaduta"',
    'COM_DECAROMEMBERSHIP_CARD_STATUS_LOST="Smarrita"',
    'COM_DECAROMEMBERSHIP_CARD_STATUS_REVOKED="Revocata"',
    'COM_DECAROMEMBERSHIP_CARD_STATUS_REPLACED="Sostituita"',
    'COM_DECAROMEMBERSHIP_FIELD_PUBLISHED="Pubblicato"',
] as $marker) {
    $expect(str_contains($it, $marker), "Italian card label missing {$marker}.");
}

$expect(str_contains($recordTemplate, "'active'=>'COM_DECAROMEMBERSHIP_CARD_STATUS_ACTIVE'"), 'Member card summary must use card-specific status labels.');
$expect(str_contains($listTemplate, "(\$field['type']??'')==='select'"), 'Records list must localize select values.');
$expect(str_contains($listTemplate, "Text::_(\$field['options'][(string)\$value])"), 'Records list must resolve configured select labels.');
$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b/i', $update), '1.9.7 migration marker must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.7 card labels contract OK\n";
