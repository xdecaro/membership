<?php

declare(strict_types=1);
defined('_JEXEC') || define('_JEXEC', 1);

$root = dirname(__DIR__);
$template = file_get_contents($root . '/component/admin/tmpl/record/default.php') ?: '';
$view = file_get_contents($root . '/component/admin/src/View/Record/HtmlView.php') ?: '';
$js = file_get_contents($root . '/component/media/js/admin.js') ?: '';
$access = file_get_contents($root . '/component/admin/access.xml') ?: '';
$controllerPath = $root . '/component/admin/src/Controller/PeopleController.php';
$controller = is_file($controllerPath) ? (file_get_contents($controllerPath) ?: '') : '';

foreach ([
    'data-membership-people-search',
    'data-membership-person-summary',
    'data-membership-person-relink',
    'jform[person_uuid]',
    'peopleOwnedFields',
] as $marker) {
    if (!str_contains($template, $marker)) {
        fwrite(STDERR, "Member People form hook missing: {$marker}\n");
        exit(1);
    }
}

foreach ([
    'public ?array $person = null;',
    'public bool $canRelinkPerson = false;',
    'getPeopleIntegrationService()',
] as $marker) {
    if (!str_contains($view, $marker)) {
        fwrite(STDERR, "Member People view contract missing: {$marker}\n");
        exit(1);
    }
}

foreach ([
    'membershipPeopleSearch',
    'data-membership-people-search',
    '250',
    'people.search',
] as $marker) {
    if (!str_contains($js, $marker)) {
        fwrite(STDERR, "Member People selector script missing: {$marker}\n");
        exit(1);
    }
}

if (!str_contains($access, 'membership.relink_person')) {
    fwrite(STDERR, "Membership relink ACL action is missing.\n");
    exit(1);
}

foreach ([
    'final class PeopleController extends BaseController',
    'function search(): void',
    'function relink(): void',
    "checkToken('get')",
    'checkToken()',
    "authorise('membership.relink_person', 'com_decaromembership')",
    'searchPeople($q, 20)',
    'relinkMember(',
    'JsonResponse',
] as $marker) {
    if (!str_contains($controller, $marker)) {
        fwrite(STDERR, "PeopleController contract missing: {$marker}\n");
        exit(1);
    }
}

if (str_contains($controller, '#__xdecaropeople_')) {
    fwrite(STDERR, "PeopleController must not query People private tables.\n");
    exit(1);
}

echo "Membership member People UI contract OK\n";
