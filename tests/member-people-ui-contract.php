<?php

declare(strict_types=1);
defined('_JEXEC') || define('_JEXEC', 1);

$root = dirname(__DIR__);
$template = file_get_contents($root . '/component/admin/tmpl/record/default.php') ?: '';
$listTemplate = file_get_contents($root . '/component/admin/tmpl/records/default.php') ?: '';
$view = file_get_contents($root . '/component/admin/src/View/Record/HtmlView.php') ?: '';
$recordsView = file_get_contents($root . '/component/admin/src/View/Records/HtmlView.php') ?: '';
$config = file_get_contents($root . '/component/admin/src/Config/MemberCoreEntities.php') ?: '';
$itLanguage = file_get_contents($root . '/component/admin/language/it-IT/com_decaromembership.ini') ?: '';
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

$assetService = file_get_contents($root . '/component/admin/src/Service/AdminAssetService.php') ?: '';
foreach (['Record' => $view, 'Records' => $recordsView] as $viewName => $assetView) {
    if (!str_contains($assetView, 'AdminAssetService::useAssets')) {
        fwrite(STDERR, "{$viewName} must use the centralized Membership asset service on Joomla 6.\n");
        exit(1);
    }
}
foreach (['registerAndUseStyle(', "'com_decaromembership/css/admin.css'", 'registerAndUseScript(', "'com_decaromembership/js/admin.js'"] as $marker) {
    if (!str_contains($assetService, $marker)) {
        fwrite(STDERR, "Membership AdminAssetService missing asset marker: {$marker}\n");
        exit(1);
    }
}

if (str_contains($listTemplate, "COM_DECAROMEMBERSHIP_NEW")) {
    fwrite(STDERR, "Records list must use the Joomla toolbar as the single New action.\n");
    exit(1);
}

if (!str_contains($config, "'published'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PUBLISHED'")) {
    fwrite(STDERR, "Member publication state must have a distinct Published label.\n");
    exit(1);
}

if (!str_contains($itLanguage, 'COM_DECAROMEMBERSHIP_FIELD_PUBLISHED="Pubblicato"')) {
    fwrite(STDERR, "Italian Published label is missing.\n");
    exit(1);
}

foreach ([
    'membershipPeopleSearch',
    'data-membership-people-search',
    'person.birth_date',
    'person.birth_place',
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
    "'birth_date' => (string) (\$row['birth_date'] ?? '')",
    "'birth_place' => (string) (\$row['birth_place'] ?? '')",
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
