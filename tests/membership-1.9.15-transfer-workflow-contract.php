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
$expect($version === '1.9.15', 'VERSION must be 1.9.15.');

$config = (string) file_get_contents($root . '/component/admin/src/Config/OperationsEntities.php');
$model = (string) file_get_contents($root . '/component/admin/src/Model/RecordModel.php');
$validator = (string) file_get_contents($root . '/component/admin/src/Service/RecordValidator.php');
$repository = (string) file_get_contents($root . '/component/admin/src/Service/RecordRepository.php');
$view = (string) file_get_contents($root . '/component/admin/src/View/Record/HtmlView.php');
$template = (string) file_get_contents($root . '/component/admin/tmpl/record/default.php');
$runtime = (string) file_get_contents($root . '/tests/organizations-runtime.php');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.15.sql');

foreach ([
    "'from_organization_uuid'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_FROM_ORGANIZATION','type'=>'organization']",
    "'to_organization_uuid'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_TO_ORGANIZATION','type'=>'organization','required'=>true]",
    "'status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_TRANSFER_STATUS','type'=>'select','required'=>true,'default'=>'requested'",
    "'delegation_status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_DELEGATION_STATUS','type'=>'select','required'=>true,'default'=>'unchecked'",
    "'sticker_status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_STICKER_STATUS','type'=>'select','required'=>true,'default'=>'unchecked'",
    "'published'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PUBLISHED','type'=>'published','default'=>1]",
] as $marker) {
    $expect(str_contains($config, $marker), "Transfer config marker missing {$marker}.");
}

foreach ([
    "trim((string) (\$input['from_organization_uuid'] ?? '')) === ''",
    "\$input['requested_at'] = \$today",
    "\$input['completed_at'] = \$today",
    'validateOptionalUuid',
    'updateMemberOrganization',
] as $marker) {
    $expect(str_contains($model . $repository, $marker), "Transfer model marker missing {$marker}.");
}

foreach ([
    'COM_DECAROMEMBERSHIP_ERROR_TRANSFER_SAME_ORGANIZATION',
    "in_array(\$delegationStatus, ['confirmed', 'not_required'], true)",
    "\$stickerStatus === '' || \$stickerStatus === 'unchecked'",
    "trim((string) (\$data['card_position'] ?? '')) === ''",
] as $marker) {
    $expect(str_contains($validator, $marker), "Transfer validation marker missing {$marker}.");
}

foreach ([
    '(\$field[\'type\'] ?? \'\') === \'organization\'',
    "data-membership-organization-picker",
    "case 'organization':",
] as $marker) {
    $expect(str_contains($view . $template, $marker), "Reusable Organizations picker marker missing {$marker}.");
}

foreach ([
    'Transfer source organization was not derived from the member organization.',
    'Transfer completed without delegation/card/sticker checks must be rejected.',
    'Completed transfer did not update member organization.',
] as $marker) {
    $expect(str_contains($runtime, $marker), "Transfer runtime marker missing {$marker}.");
}

foreach ([
    'from_organization_uuid',
    'to_organization_uuid',
    'sticker_status',
] as $marker) {
    $expect(str_contains($update, $marker), "1.9.15 migration marker missing {$marker}.");
}

$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b/i', $update), '1.9.15 migration must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.15 transfer workflow contract OK\n";
