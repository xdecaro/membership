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
$expect($version === '1.7.0', 'VERSION must be 1.7.0.');

$install = (string) file_get_contents($root . '/component/admin/sql/install.mysql.utf8mb4.sql');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.7.0.sql');
$config = (string) file_get_contents($root . '/component/admin/src/Config/MemberCoreEntities.php');
$model = (string) file_get_contents($root . '/component/admin/src/Model/RecordModel.php');
$template = (string) file_get_contents($root . '/component/admin/tmpl/record/default.php');
$organizations = (string) file_get_contents($root . '/component/admin/src/Service/OrganizationsIntegrationService.php');
$history = (string) file_get_contents($root . '/component/admin/src/Service/MembershipHistoryService.php');
$peopleLink = (string) file_get_contents($root . '/component/admin/src/Service/MemberPeopleLinkService.php');
$package = (string) file_get_contents($root . '/package/pkg_decaromembership.xml');

foreach (['organization_uuid', 'idx_member_organization_uuid', 'old_organization_uuid', 'new_organization_uuid'] as $marker) {
    $expect(str_contains($install, $marker), "Install schema missing {$marker}.");
}
foreach (['organization_uuid','old_organization_uuid','new_organization_uuid'] as $marker) {
    $expect(str_contains($update, $marker), "1.7.0 migration missing {$marker}.");
}
$expect(!preg_match('/\\b(?:DROP\\s+TABLE|TRUNCATE\\s+TABLE)\\b/i', $update), '1.7.0 migration must be non-destructive.');

$expect(str_contains($config, "'organization_uuid'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_ORGANIZATION','type'=>'organization']"), 'Member organization field must exist and stay optional.');
$expect(!str_contains($config, "'organization_uuid'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_ORGANIZATION','type'=>'organization','required'=>true"), 'Organizations must never be required to create a member.');
$expect(str_contains($config, "'list'=>['member_number','last_name','first_name','category_id','status','card_number','email']"), 'Primary member list must not depend on a legacy location.');

foreach (["bootComponent('com_xdecaroorganizations')",'getOrganizationProviderService','searchOrganizations','validateOptionalUuid'] as $marker) {
    $expect(str_contains($organizations, $marker), "Organizations public integration missing {$marker}.");
}
$expect(!str_contains($organizations, '#__xdecaroorganizations_'), 'Membership must not access Organizations private tables.');
$expect(!str_contains($package, 'com_xdecaroorganizations'), 'Organizations must remain an optional package dependency.');

foreach (['COM_DECAROMEMBERSHIP_MEMBER_ESSENTIALS','COM_DECAROMEMBERSHIP_ORGANIZATION_NONE','COM_DECAROMEMBERSHIP_MEMBER_ADVANCED','COM_DECAROMEMBERSHIP_LOCATION_LEGACY_HELP'] as $marker) {
    $expect(str_contains($template, $marker), "Simplified member form missing {$marker}.");
}
$expect(str_contains($template, 'name="jform[organization_uuid]"'), 'Member form must expose the optional organization field when available.');
$expect(!str_contains($template, 'name="jform[organization_uuid]" required'), 'Organization field must not be required.');

$expect(str_contains($model, 'validateOptionalUuid'), 'Member save must validate a submitted organization through the public provider.');
$expect(str_contains($model, "!array_key_exists('organization_uuid', \$input)"), 'Existing organization link must be preserved if Organizations is temporarily unavailable.');
$expect(str_contains($history, "'organization_uuid'"), 'Organization changes must be included in Membership history.');
$expect(str_contains($history, "'organization_uuid' => 'organization_change'"), 'Organization-only changes must have a dedicated history event.');
$expect(!preg_match('/->bind\\([^\\n]+\\$old\\?->/', $history), 'Membership history must not bind nullsafe property expressions by reference.');
$expect(!preg_match('/->bind\\([^\\n]+\\$new->/', $history), 'Membership history must not bind object property expressions by reference.');
$expect(str_contains($history, '$oldOrganizationUuid') && str_contains($history, '$newOrganizationUuid'), 'Organization history bindings must use stable local variables.');

$expect(str_contains($peopleLink, 'People person is required for a new member.'), 'People must remain required for a new member.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.7.0 simple member / optional Organizations contract OK\n";
