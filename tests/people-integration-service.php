<?php

declare(strict_types=1);
defined('_JEXEC') || define('_JEXEC', 1);

$root = dirname(__DIR__);
$servicePath = $root . '/component/admin/src/Service/PeopleIntegrationService.php';
if (!is_file($servicePath)) {
    fwrite(STDERR, "PeopleIntegrationService is missing.\n");
    exit(1);
}

$service = file_get_contents($servicePath) ?: '';
foreach ([
    "MINIMUM_CORE_VERSION = '2.0.1'",
    "MINIMUM_PEOPLE_VERSION = '1.2.15'",
    'function isAvailable(): bool',
    'function dependencyStatus(): array',
    'function getPerson(string $uuid, bool $sensitive = false): ?array',
    'function searchPeople(string $search, int $limit = 50): array',
    'function getPeopleByUuids(array $uuids): array',
    'function findByUserIdUnique(int $userId): ?array',
    'function openPersonUrl(string $uuid): string',
    "bootComponent('com_xdecaropeople')",
    "method_exists($component, 'getPersonProviderService')",
    "searchPeople(['user_id' => $userId], 2, false)",
    'count($rows) === 1 ? $rows[0] : null',
] as $marker) {
    if (!str_contains($service, $marker)) {
        fwrite(STDERR, "PeopleIntegrationService contract missing: {$marker}\n");
        exit(1);
    }
}

if (str_contains($service, '#__xdecaropeople_')) {
    fwrite(STDERR, "PeopleIntegrationService must not query People private tables.\n");
    exit(1);
}

$provider = file_get_contents($root . '/component/admin/services/provider.php') ?: '';
$component = file_get_contents($root . '/component/admin/src/Extension/MembershipComponent.php') ?: '';
foreach (['PeopleIntegrationService::class', 'setPeopleIntegrationService'] as $marker) {
    if (!str_contains($provider, $marker)) {
        fwrite(STDERR, "Membership DI provider missing {$marker}.\n");
        exit(1);
    }
}
foreach (['setPeopleIntegrationService', 'getPeopleIntegrationService'] as $marker) {
    if (!str_contains($component, $marker)) {
        fwrite(STDERR, "MembershipComponent missing {$marker}.\n");
        exit(1);
    }
}

echo "Membership People adapter contract OK\n";
