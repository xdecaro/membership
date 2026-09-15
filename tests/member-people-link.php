<?php

declare(strict_types=1);
defined('_JEXEC') || define('_JEXEC', 1);

$root = dirname(__DIR__);
$linkPath = $root . '/component/admin/src/Service/MemberPeopleLinkService.php';
if (!is_file($linkPath)) {
    fwrite(STDERR, "MemberPeopleLinkService is missing.\n");
    exit(1);
}

$link = file_get_contents($linkPath) ?: '';
foreach ([
    'function validateForSave(int $memberId, ?object $old, array $data): array',
    'function linkLegacyMember(int $memberId, string $uuid, int $userId, string $now): void',
    'function relinkMember(int $memberId, string $uuid, int $userId, string $now): void',
    'People person is required',
    'People person was not found',
    'already linked',
    'requires the explicit relink action',
    "'first_name','last_name','birth_date','birth_place','tax_code','address','city'",
    "'province','postal_code','country','email','phone','user_id'",
    'function stripPeopleOwnedFields(array $data): array',
    'if ($memberId > 0 && $old !== null && $submittedUuid === \'\')',
    'return $this->stripPeopleOwnedFields($data);',
] as $marker) {
    if (!str_contains($link, $marker)) {
        fwrite(STDERR, "Member People link contract missing: {$marker}\n");
        exit(1);
    }
}

$repository = file_get_contents($root . '/component/admin/src/Service/RecordRepository.php') ?: '';
foreach ([
    'function findMemberIdByPersonUuid(string $uuid, int $excludeId = 0): ?int',
    'function updateMemberPersonUuid(int $memberId, string $uuid, string $modified, int $userId): void',
    'function loadUnlinkedMembersWithUserId(): array',
] as $marker) {
    if (!str_contains($repository, $marker)) {
        fwrite(STDERR, "RecordRepository People helper missing: {$marker}\n");
        exit(1);
    }
}

$model = file_get_contents($root . '/component/admin/src/Model/RecordModel.php') ?: '';
if (!str_contains($model, "if (\$entity === 'members')")) {
    fwrite(STDERR, "RecordModel must isolate People link rules to members.\n");
    exit(1);
}
if (!str_contains($model, 'validateForSave($id, $old, $data)')) {
    fwrite(STDERR, "RecordModel does not apply MemberPeopleLinkService.\n");
    exit(1);
}

$audit = file_get_contents($root . '/component/admin/src/Service/AuditService.php') ?: '';
if (!str_contains($audit, 'function personLink(')
    || !str_contains($audit, "(object) ['person_uuid' => \$oldUuid]")
    || !str_contains($audit, "(object) ['person_uuid' => \$newUuid]")) {
    fwrite(STDERR, "Membership audit must record People link changes as UUID-only values.\n");
    exit(1);
}

echo "Membership member-People link contract OK\n";
