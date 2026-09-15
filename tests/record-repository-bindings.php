<?php

declare(strict_types=1);
defined('_JEXEC') || define('_JEXEC', 1);

$source = file_get_contents(__DIR__ . '/../component/admin/src/Service/RecordRepository.php') ?: '';

foreach ([
    'private function bindType(mixed $value): string',
    '$bindings[$ph] = $value;',
    '$q->bind($ph, $bindings[$ph], $this->bindType($value));',
    'ParameterType::NULL',
    'ParameterType::INTEGER',
    'ParameterType::BOOLEAN',
    'ParameterType::STRING',
] as $marker) {
    if (!str_contains($source, $marker)) {
        fwrite(STDERR, "RecordRepository stable binding contract missing: {$marker}\n");
        exit(1);
    }
}

if (str_contains($source, '$q->bind($ph, $value);')) {
    fwrite(STDERR, "RecordRepository must not bind a reused foreach variable by reference.\n");
    exit(1);
}

echo "Membership RecordRepository stable binding contract OK\n";
