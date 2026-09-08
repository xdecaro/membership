<?php
// Make the Joomla guard pass in the isolated CLI test.
define('_JEXEC', 1);
require_once __DIR__ . '/../component/admin/src/Service/CoreIntegrationService.php';

$service = new \Xdecaro\Component\Decaromembership\Administrator\Service\CoreIntegrationService();
if ($service->isAvailable()) {
    fwrite(STDERR, "Core unexpectedly available in isolated smoke test.\n");
    exit(1);
}

try {
    $service->createEntityReference('member', 1);
    fwrite(STDERR, "Expected controlled RuntimeException.\n");
    exit(1);
} catch (RuntimeException $e) {
    if (!str_contains($e->getMessage(), 'Core by xdecaro')) {
        fwrite(STDERR, "Unexpected RuntimeException message.\n");
        exit(1);
    }
}

echo "Membership optional Core smoke test OK\n";
