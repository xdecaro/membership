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
$expect($version === '1.9.9', 'VERSION must be 1.9.9.');

$controller = (string) file_get_contents($root . '/component/admin/src/Controller/RecordController.php');
$model = (string) file_get_contents($root . '/component/admin/src/Model/RecordModel.php');
$validator = (string) file_get_contents($root . '/component/admin/src/Service/RecordValidator.php');
$it = (string) file_get_contents($root . '/component/admin/language/it-IT/com_decaromembership.ini');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.9.sql');

foreach ([
    "setUserState('com_decaromembership.record.'.\$entity.'.'.\$id.'.data',\$data)",
    "\$data=(array)\$this->input->get('jform',[],'array')",
] as $marker) {
    $expect(str_contains($controller, $marker), "RecordController validation-state marker missing {$marker}.");
}

foreach ([
    "getUserState(\$stateKey)",
    "setUserState(\$stateKey, null)",
    "\$item = (object) \$submitted",
] as $marker) {
    $expect(str_contains($model, $marker), "RecordModel restored-form marker missing {$marker}.");
}

$expect(str_contains($validator, "Text::_((string) (\$field['label'] ?? \$name))"), 'Validator must resolve the human field label.');
$expect(str_contains($validator, "Text::sprintf('COM_DECAROMEMBERSHIP_ERROR_REQUIRED', \$label)"), 'Validator must include the field label in the required-field error.');
$expect(str_contains($it, 'COM_DECAROMEMBERSHIP_ERROR_REQUIRED="Campo obbligatorio mancante: %s."'), 'Italian missing-field error must name the field.');
$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b/i', $update), '1.9.9 migration marker must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.9 validation preservation contract OK\n";
