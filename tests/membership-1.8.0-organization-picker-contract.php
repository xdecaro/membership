<?php

declare(strict_types=1);
defined('_JEXEC') || define('_JEXEC', 1);

$root = dirname(__DIR__);
$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$version = trim((string) file_get_contents($root . '/VERSION'));
$expect($version === '1.8.0', 'VERSION must be 1.8.0.');

$view = (string) file_get_contents($root . '/component/admin/src/View/Record/HtmlView.php');
$template = (string) file_get_contents($root . '/component/admin/tmpl/record/default.php');
$script = (string) file_get_contents($root . '/component/media/js/admin.js');
$css = (string) file_get_contents($root . '/component/media/css/admin.css');
$italian = (string) file_get_contents($root . '/component/admin/language/it-IT/com_decaromembership.ini');
$english = (string) file_get_contents($root . '/component/admin/language/en-GB/com_decaromembership.ini');
$french = (string) file_get_contents($root . '/component/admin/language/fr-FR/com_decaromembership.ini');

foreach (['buildOrganizationOptions', "organization['parent_id']", "organization['depth']", "organization['path']", 'Orphans/cycles'] as $marker) {
    $expect(str_contains($view, $marker), "Hierarchical Organizations view logic missing {$marker}.");
}
foreach ([
    'data-membership-organization-search',
    'data-membership-organization-select',
    'data-membership-organization-path',
    'organizationTypeLabel',
    "str_repeat(' '",
    'COM_DECAROMEMBERSHIP_ORGANIZATION_SEARCH_PLACEHOLDER',
] as $marker) {
    $expect(str_contains($template, $marker), "Organizations picker template missing {$marker}.");
}
foreach ([
    'initOrganizationPicker',
    'option.hidden',
    'option.disabled',
    'data-membership-organization-search',
    'data-membership-organization-select',
    'normalize',
] as $marker) {
    $expect(str_contains($script, $marker), "Organizations picker script missing {$marker}.");
}
$expect(str_contains($css, '.dm-organization-picker'), 'Organizations picker CSS is missing.');

foreach ([
    'ORGANIZATION',
    'ASSOCIATION',
    'CLUB',
    'FEDERATION',
    'COMPANY',
    'PUBLIC_BODY',
    'SCHOOL',
    'SPONSOR',
    'SUPPLIER',
] as $type) {
    $key = 'COM_DECAROMEMBERSHIP_ORGANIZATION_TYPE_' . $type . '=';
    foreach (['it' => $italian, 'en' => $english, 'fr' => $french] as $language => $source) {
        $expect(str_contains($source, $key), "Missing {$language} organization type translation {$key}.");
    }
}

foreach ([
    'COM_DECAROMEMBERSHIP_ORGANIZATION_SEARCH=',
    'COM_DECAROMEMBERSHIP_ORGANIZATION_SEARCH_PLACEHOLDER=',
    'COM_DECAROMEMBERSHIP_ORGANIZATION_SEARCH_EMPTY=',
] as $key) {
    foreach (['it' => $italian, 'en' => $english, 'fr' => $french] as $language => $source) {
        $expect(str_contains($source, $key), "Missing {$language} organization search translation {$key}.");
    }
}

$marker = $root . '/component/admin/sql/updates/mysql/1.8.0.sql';
$expect(is_file($marker), 'Membership 1.8.0 SQL marker is missing.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.8.0 hierarchical Organizations picker contract OK\n";
