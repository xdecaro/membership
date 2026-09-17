<?php

declare(strict_types=1);
defined('_JEXEC') || define('_JEXEC', 1);

$source = file_get_contents(__DIR__ . '/../component/admin/src/Service/AuditService.php') ?: '';

foreach ([
    '$oldValues = $old ? json_encode(',
    '$newValues = $new ? json_encode(',
    "->bind(':old_values', \$oldValues)",
    "->bind(':new_values', \$newValues)",
] as $marker) {
    if (!str_contains($source, $marker)) {
        fwrite(STDERR, "AuditService stable binding contract missing: {$marker}\n");
        exit(1);
    }
}

if (str_contains($source, "->bind(':old_values', \$old ?") || str_contains($source, "->bind(':new_values', \$new ?")) {
    fwrite(STDERR, "AuditService must not bind expressions by reference.\n");
    exit(1);
}

echo "Membership AuditService stable binding contract OK\n";
