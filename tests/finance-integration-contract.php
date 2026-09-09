<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$runtimeRoots = ['component', 'plugins', 'package'];
$bannedTablePrefix = '#__' . 'decarofinance_';
$bannedClassFragment = '\\component\\decarofinance\\';

foreach ($runtimeRoots as $runtimeRoot) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $runtimeRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            fwrite(STDERR, 'Cannot read ' . $file->getPathname() . "\n");
            exit(1);
        }

        if (str_contains($content, $bannedTablePrefix)) {
            fwrite(STDERR, 'Membership runtime accesses a Finance private table: ' . $file->getPathname() . "\n");
            exit(1);
        }

        if (str_contains(strtolower($content), $bannedClassFragment)) {
            fwrite(STDERR, 'Membership runtime depends on a Finance implementation class: ' . $file->getPathname() . "\n");
            exit(1);
        }
    }
}

$bridgePath = $root . '/component/admin/src/Service/CrossProductIntegrationService.php';
$bridge = file_get_contents($bridgePath);
if ($bridge === false) {
    fwrite(STDERR, "Membership Finance bridge is missing.\n");
    exit(1);
}

foreach ([
    "bootComponent('com_decarofinance')",
    'getFinanceService',
    'upsertObligation',
    'upsertPayment',
    'allocatePaymentIdempotent',
] as $marker) {
    if (!str_contains($bridge, $marker)) {
        fwrite(STDERR, 'Membership Finance bridge is missing public API marker: ' . $marker . "\n");
        exit(1);
    }
}

echo "Membership Finance integration contract OK\n";
