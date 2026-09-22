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
$expect($version === '1.9.16', 'VERSION must be 1.9.16.');

$model = (string) file_get_contents($root . '/component/admin/src/Model/RecordModel.php');
$template = (string) file_get_contents($root . '/component/admin/tmpl/record/default.php');
$js = (string) file_get_contents($root . '/component/media/js/admin.js');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.16.sql');

foreach ([
    '$db->quoteName(\'organization_uuid\')',
    'if ($id < 1 && (int) ($input[\'member_id\'] ?? 0) > 0)',
    '$input[\'from_organization_uuid\'] = $memberOrganization',
] as $marker) {
    $expect(str_contains($model, $marker), "Transfer source backend marker missing {$marker}.");
}

foreach ([
    'data-organization-uuid=',
] as $marker) {
    $expect(str_contains($template, $marker), "Member relation organization metadata missing {$marker}.");
}

foreach ([
    'const initTransferSourceOrganization = () => {',
    "document.getElementById('jform_member_id')",
    "document.getElementById('jform_from_organization_uuid')",
    "option?.dataset?.organizationUuid",
    "member.addEventListener('change', applyMemberOrganization)",
    'initTransferSourceOrganization();',
] as $marker) {
    $expect(str_contains($js, $marker), "Transfer source UI marker missing {$marker}.");
}

$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b/i', $update), '1.9.16 migration marker must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.16 transfer source autofill contract OK\n";
