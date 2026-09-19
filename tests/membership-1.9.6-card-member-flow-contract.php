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
$expect($version === '1.9.6', 'VERSION must be 1.9.6.');

$model = (string) file_get_contents($root . '/component/admin/src/Model/RecordModel.php');
$listModel = (string) file_get_contents($root . '/component/admin/src/Model/RecordsModel.php');
$view = (string) file_get_contents($root . '/component/admin/src/View/Record/HtmlView.php');
$template = (string) file_get_contents($root . '/component/admin/tmpl/record/default.php');
$it = (string) file_get_contents($root . '/component/admin/language/it-IT/com_decaromembership.ini');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.6.sql');

foreach ([
    'getRelationOptions(string $entity, int $includeId = 0)',
    "new PeopleIntegrationService($db)",
    "COM_DECAROMEMBERSHIP_MEMBER_FALLBACK_LABEL",
] as $marker) {
    $expect(str_contains($model, $marker), "RecordModel member relation flow missing {$marker}.");
}

foreach ([
    "\$this->entity === 'cards'",
    "\$app->input->getInt('member_id')",
    "getRelationOptions(\$field['relation'], \$selectedId)",
] as $marker) {
    $expect(str_contains($view, $marker), "Record HtmlView card prefill missing {$marker}.");
}

foreach ([
    "view=record&entity=cards&id=0&member_id=",
    'COM_DECAROMEMBERSHIP_MEMBER_CARD_CREATE',
] as $marker) {
    $expect(str_contains($template, $marker), "Member card action missing {$marker}.");
}

foreach ([
    'getPeopleByUuids',
    "person_uuid",
    "display_name",
] as $marker) {
    $expect(str_contains($listModel, $marker), "RecordsModel People-backed member labels missing {$marker}.");
}

$expect(str_contains($it, 'COM_DECAROMEMBERSHIP_MEMBER_CARD_CREATE="Crea tessera"'), 'Italian create-card label missing.');
$expect(str_contains($it, 'COM_DECAROMEMBERSHIP_MEMBER_FALLBACK_LABEL="Socio #%s"'), 'Italian fallback member label missing.');
$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b/i', $update), '1.9.6 migration marker must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.6 card member flow contract OK\n";
