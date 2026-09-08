<?php

define('_JEXEC', 1);

require_once __DIR__ . '/../component/admin/src/Service/CoreIntegrationService.php';

use Xdecaro\Component\Decaromembership\Administrator\Service\CoreIntegrationService;

$service = new CoreIntegrationService();

if ($service->isAvailable()) {
    throw new \RuntimeException('Core should not be available in the isolated Membership smoke test.');
}

$controlledFailure = false;

try {
    $service->createEntityReference('member', 1);
} catch (\RuntimeException $exception) {
    $controlledFailure = str_contains($exception->getMessage(), 'Xdecaro Core integration is unavailable');
}

if (!$controlledFailure) {
    throw new \RuntimeException('Membership must fail gracefully when optional Core is unavailable.');
}

echo "Membership optional Core integration smoke test passed.\n";
