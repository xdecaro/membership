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
$expect($version === '1.6.0', 'VERSION must be 1.6.0.');

$install = (string) file_get_contents($root . '/component/admin/sql/install.mysql.utf8mb4.sql');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.6.0.sql');
$members = (string) file_get_contents($root . '/component/admin/src/Config/MemberCoreEntities.php');
$transfers = (string) file_get_contents($root . '/component/admin/src/Config/OperationsEntities.php');
$core = (string) file_get_contents($root . '/component/admin/src/Service/CoreIntegrationService.php');
$component = (string) file_get_contents($root . '/component/admin/src/Extension/MembershipComponent.php');
$recordModel = (string) file_get_contents($root . '/component/admin/src/Model/RecordModel.php');
$recordValidator = (string) file_get_contents($root . '/component/admin/src/Service/RecordValidator.php');
$personService = (string) file_get_contents($root . '/component/admin/src/Service/PersonMembershipService.php');
$eligibility = (string) file_get_contents($root . '/component/admin/src/Service/MembershipEligibilityService.php');
$history = (string) file_get_contents($root . '/component/admin/src/Service/MembershipHistoryService.php');

foreach (['application_date','admission_date','status_effective_date','cessation_date','cessation_reason','voting_active','voting_passive'] as $field) {
    $expect(str_contains($install, $field), "Install schema missing {$field}.");
    $expect(str_contains($update, $field), "1.6.0 migration missing {$field}.");
}
$expect(str_contains($install, '#__decaromembership_member_history'), 'Install schema must include member history.');
$expect(str_contains($update, '#__decaromembership_member_history'), '1.6.0 migration must create member history.');
$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i', $update), '1.6.0 migration must be non-destructive.');

foreach (['in_review','admitted','lapsed','resigned','expelled','deceased','transferred','ceased'] as $status) {
    $expect(str_contains($members, "'{$status}'"), "Membership lifecycle status missing: {$status}.");
}
$expect(str_contains($transfers, "'effective_at'"), 'Transfer effective date field is missing.');
$expect(str_contains($recordValidator, "empty(\$data['effective_at'])"), 'Completed transfers must require an effective date.');
$expect(str_contains($recordModel, 'updateMemberLocation'), 'Completed transfers must update the Membership location.');
$expect(str_contains($recordModel, 'recordMemberChange'), 'Membership lifecycle changes must be written to history.');

$expect(str_contains($core, "membership.person_memberships"), 'Public person-memberships capability is missing.');
$expect(str_contains($core, "membership.eligibility"), 'Public eligibility capability is missing.');
$expect(str_contains($component, 'getPersonMembershipService'), 'Membership component must expose person membership service.');
$expect(str_contains($component, 'getMembershipEligibilityService'), 'Membership component must expose eligibility service.');
$expect(str_contains($personService, 'getMembershipsByPersonUuid'), 'Person Membership public lookup is missing.');
$expect(str_contains($eligibility, 'getSnapshot'), 'Eligibility snapshot is missing.');
$expect(str_contains($eligibility, 'isFeeCurrent'), 'Fee status helper is missing.');
$expect(str_contains($history, '#__decaromembership_member_history'), 'Lifecycle service must use dedicated member history.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.6.0 lifecycle contract OK\n";
