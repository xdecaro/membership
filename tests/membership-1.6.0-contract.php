<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$version = trim((string) file_get_contents($root . '/VERSION'));
$assert($version === '1.6.0', 'Membership VERSION must be 1.6.0.');

$schema = (string) file_get_contents($root . '/component/admin/sql/install.mysql.utf8mb4.sql');
$upgrade = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.6.0.sql');
$memberConfig = (string) file_get_contents($root . '/component/admin/src/Config/MemberCoreEntities.php');
$caseConfig = (string) file_get_contents($root . '/component/admin/src/Config/CaseEntities.php');
$eligibility = (string) file_get_contents($root . '/component/admin/src/Service/MembershipEligibilityService.php');
$history = (string) file_get_contents($root . '/component/admin/src/Service/MembershipPersonHistoryService.php');
$core = (string) file_get_contents($root . '/component/admin/src/Service/CoreIntegrationService.php');
$component = (string) file_get_contents($root . '/component/admin/src/Extension/MembershipComponent.php');
$provider = (string) file_get_contents($root . '/component/admin/services/provider.php');
$config = (string) file_get_contents($root . '/component/admin/config.xml');

foreach ([
    'current_period_started_on','ended_on','status_reason','rights_status',
    'can_vote_override','can_candidate_override','seniority_credit_days',
    '#__decaromembership_member_history',
] as $marker) {
    $assert(str_contains($schema, $marker), "Membership install schema missing {$marker}.");
    $assert(str_contains($upgrade, $marker), "Membership 1.6.0 migration missing {$marker}.");
}

foreach (['resigned','expelled','deceased','transferred','rights_status'] as $marker) {
    $assert(str_contains($memberConfig, $marker), "Membership member lifecycle config missing {$marker}.");
}
$assert(str_contains($caseConfig, "'readmission'"), 'Membership must expose a readmission case type.');

foreach ([
    'function isActiveMember(int $memberId): bool',
    'function isFeeCurrent(int $memberId, ?string $associationYear = null): bool',
    'function canVote(int $memberId): bool',
    'function canBeCandidate(int $memberId): bool',
    'function getMembershipSeniorityDays(int $memberId): int',
] as $marker) {
    $assert(str_contains($eligibility, $marker), "Membership eligibility service missing {$marker}.");
}

foreach ([
    'function recordTransition(',
    'function getHistoryByPersonUuid(string $personUuid): array',
    '#__decaromembership_member_history',
    '#__decaromembership_transfers',
] as $marker) {
    $assert(str_contains($history, $marker), "Membership person history service missing {$marker}.");
}
$assert(!str_contains($history, '#__xdecaropeople_'), 'Membership person history service must not query People private tables.');
$assert(str_contains($core, "'membership.people_history','1'"), 'Membership Core capability membership.people_history v1 is missing.');
$assert(str_contains($component, 'getMembershipPersonHistoryService'), 'Membership component must expose its public person history service.');
$assert(str_contains($provider, 'MembershipPersonHistoryService::class'), 'Membership DI provider must register the person history service.');

foreach ([
    'minimum_seniority_days_vote','minimum_seniority_days_candidate',
    'voting_require_current_fee','candidacy_require_current_fee',
] as $marker) {
    $assert(str_contains($config, $marker), "Membership eligibility configuration missing {$marker}.");
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "Membership 1.6.0 lifecycle, eligibility and People-history contract OK\n";
