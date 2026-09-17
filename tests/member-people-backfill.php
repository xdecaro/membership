<?php

declare(strict_types=1);
defined('_JEXEC') || define('_JEXEC', 1);

$root = dirname(__DIR__);
$servicePath = $root . '/component/admin/src/Service/MemberPeopleBackfillService.php';
if (!is_file($servicePath)) {
    fwrite(STDERR, "MemberPeopleBackfillService is missing.\n");
    exit(1);
}

$service = file_get_contents($servicePath) ?: '';
foreach ([
    'final class MemberPeopleBackfillService',
    'function run(int $userId, string $now): array',
    'loadUnlinkedMembersWithUserId()',
    'findByUserIdUnique((int) $member->user_id)',
    'findMemberIdByPersonUuid($uuid)',
    'updateMemberPersonUuid((int) $member->id, $uuid, $now, $userId)',
    "personLink((int) \$member->id, 'people_backfill'",
    "'linked' => \$linked",
    "'skipped' => \$skipped",
    "'ambiguous' => \$ambiguous",
] as $marker) {
    if (!str_contains($service, $marker)) {
        fwrite(STDERR, "Member People backfill contract missing: {$marker}\n");
        exit(1);
    }
}

$script = file_get_contents($root . '/package/script.php') ?: '';
foreach ([
    "['install', 'update', 'discover_install']",
    'MemberPeopleBackfillService',
    'getPeopleIntegrationService()',
    'people_backfill',
    'getIdentity()',
    'People backfill deferred',
] as $marker) {
    if (!str_contains($script, $marker)) {
        fwrite(STDERR, "Membership package backfill hook missing: {$marker}\n");
        exit(1);
    }
}

if (str_contains($script, 'getIdentity()->id')) {
    fwrite(STDERR, "Membership installer must not dereference a missing CLI identity.\n");
    exit(1);
}

echo "Membership member-People backfill contract OK\n";
