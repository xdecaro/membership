<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$service = (string) file_get_contents($root . '/component/admin/src/Service/PeopleIntegrationService.php');
$runtime = (string) file_get_contents($root . '/tests/people-runtime.php');

foreach ([
    "getPeopleByUuids(array_values(\$normalized), false)",
    "foreach (\$normalized as \$uuid)",
    "getPerson(\$uuid, false)",
    "isset(\$resolved[\$uuid])",
    "resolvedUuid === \$uuid",
] as $marker) {
    if (!str_contains($service, $marker)) {
        fwrite(STDERR, "Membership 1.9.17 People batch fallback missing: {$marker}\n");
        exit(1);
    }
}

if (str_contains($service, '#__xdecaropeople_')) {
    fwrite(STDERR, "Membership 1.9.17 fallback must not query People private tables.\n");
    exit(1);
}

foreach ([
    'CI Batch Alpha',
    'CI Batch Beta',
    'Membership People batch fallback did not resolve all requested identities.',
] as $marker) {
    if (!str_contains($runtime, $marker)) {
        fwrite(STDERR, "Membership 1.9.17 runtime regression missing: {$marker}\n");
        exit(1);
    }
}

echo "Membership 1.9.17 People batch fallback contract OK\n";
