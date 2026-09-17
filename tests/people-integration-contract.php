<?php

declare(strict_types=1);
defined('_JEXEC') || define('_JEXEC', 1);

$root = dirname(__DIR__);
$installPath = $root . '/component/admin/sql/install.mysql.utf8mb4.sql';
$updatePath = $root . '/component/admin/sql/updates/mysql/1.5.0.sql';

if (!is_file($updatePath)) {
    fwrite(STDERR, "Membership 1.5.0 schema update is missing.\n");
    exit(1);
}

$installSql = file_get_contents($installPath) ?: '';
$updateSql = file_get_contents($updatePath) ?: '';

$requiredInstall = [
    '`person_uuid` CHAR(36) NULL',
    'UNIQUE KEY `uq_member_person_uuid` (`person_uuid`)',
    '`first_name` VARCHAR(190) NULL',
    '`last_name` VARCHAR(190) NULL',
];
foreach ($requiredInstall as $marker) {
    if (!str_contains($installSql, $marker)) {
        fwrite(STDERR, "Membership clean schema missing: {$marker}\n");
        exit(1);
    }
}

foreach ([
    'ADD COLUMN `person_uuid` CHAR(36) NULL',
    'MODIFY `first_name` VARCHAR(190) NULL',
    'MODIFY `last_name` VARCHAR(190) NULL',
    'ADD UNIQUE KEY `uq_member_person_uuid` (`person_uuid`)',
] as $marker) {
    if (!str_contains($updateSql, $marker)) {
        fwrite(STDERR, "Membership 1.5.0 update missing: {$marker}\n");
        exit(1);
    }
}

$source = '';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/component/admin/src'));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $source .= "\n" . (file_get_contents($file->getPathname()) ?: '');
}

if (str_contains($source, '#__xdecaropeople_')) {
    fwrite(STDERR, "Membership must not query People private tables.\n");
    exit(1);
}
if (preg_match('/People\\\\Administrator\\\\(Model|Table)\\\\/', $source)) {
    fwrite(STDERR, "Membership must not depend on private People Model/Table classes.\n");
    exit(1);
}

echo "Membership People schema/boundary contract OK\n";
