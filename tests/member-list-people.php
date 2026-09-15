<?php

declare(strict_types=1);
defined('_JEXEC') || define('_JEXEC', 1);

$root = dirname(__DIR__);
$model = file_get_contents($root . '/component/admin/src/Model/RecordsModel.php') ?: '';
$view = file_get_contents($root . '/component/admin/src/View/Records/HtmlView.php') ?: '';
$template = file_get_contents($root . '/component/admin/tmpl/records/default.php') ?: '';

foreach ([
    'function resolvePeopleForItems(array $items): array',
    'getPeopleByUuids(array_values($uuids))',
    "setState('filter.people_link'",
    "getState('filter.people_link'",
    "person_uuid') . ' IS NULL'",
    "person_uuid') . ' IS NOT NULL'",
    'searchPeople($search, 200)',
    "a.person_uuid",
] as $marker) {
    if (!str_contains($model, $marker)) {
        fwrite(STDERR, "Member list People contract missing: {$marker}\n");
        exit(1);
    }
}

if (str_contains($model, '#__xdecaropeople_')) {
    fwrite(STDERR, "Member list must not join/query People private tables.\n");
    exit(1);
}

foreach (['public array $peopleMap = [];', 'resolvePeopleForItems($this->items)'] as $marker) {
    if (!str_contains($view, $marker)) {
        fwrite(STDERR, "Records view People map contract missing: {$marker}\n");
        exit(1);
    }
}

foreach ([
    'people_link',
    'COM_DECAROMEMBERSHIP_PEOPLE_LINK_ALL',
    'COM_DECAROMEMBERSHIP_PEOPLE_LINK_LINKED',
    'COM_DECAROMEMBERSHIP_PEOPLE_LINK_UNLINKED',
    'COM_DECAROMEMBERSHIP_PEOPLE_UNAVAILABLE',
    'COM_DECAROMEMBERSHIP_PEOPLE_UNLINKED',
] as $marker) {
    if (!str_contains($template, $marker)) {
        fwrite(STDERR, "Member list UI contract missing: {$marker}\n");
        exit(1);
    }
}

echo "Membership People-backed member list contract OK\n";
