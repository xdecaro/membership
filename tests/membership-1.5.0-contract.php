<?php

declare(strict_types=1);
defined('_JEXEC') || define('_JEXEC', 1);

$root = dirname(__DIR__);
$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$version = trim((string) file_get_contents($root . '/VERSION'));
$expect(version_compare($version, '1.6.0', '>='), 'VERSION must be 1.6.0 or newer.');

foreach ([
    'component/decaromembership.xml',
    'package/pkg_decaromembership.xml',
    'plugins/xdecaroanalytics/decaromembership/decaromembership.xml',
    'plugins/task/decaromembership/decaromembership.xml',
] as $manifestPath) {
    $xml = simplexml_load_file($root . '/' . $manifestPath);
    $expect($xml !== false && (string) $xml->version === $version, "{$manifestPath} must match VERSION.");
}

$componentManifest = simplexml_load_file($root . '/component/decaromembership.xml');
if ($componentManifest !== false) {
    foreach ($componentManifest->administration->submenu->menu as $menu) {
        $link = trim((string) $menu['link']);
        if ($link !== '') {
            $expect(
                str_contains($link, 'option=com_decaromembership'),
                'Custom Membership submenu links must include option=com_decaromembership.'
            );
        }
    }
}

$assets = json_decode((string) file_get_contents($root . '/component/media/joomla.asset.json'), true);
$expect(($assets['version'] ?? '') === '1.6.0', 'Web Asset version must match VERSION.');

$installer = (string) file_get_contents($root . '/package/script.php');
$expect(str_contains($installer, "MINIMUM_CORE_VERSION = '2.0.1'"), 'Core minimum must be 2.0.1.');
$expect(str_contains($installer, "MINIMUM_PEOPLE_VERSION = '1.2.15'"), 'People minimum must be 1.2.15.');
$expect(str_contains($installer, "'pkg_core', 'pkg_xdecarocore'"), 'Installer must accept canonical and legacy Core package identities.');
$expect(str_contains($installer, "'pkg_people', 'pkg_xdecaropeople'"), 'Installer must accept canonical and legacy People package identities.');
$expect(str_contains($installer, 'memberCount'), 'Installer must detect whether legacy members exist before People backfill.');
$expect(str_contains($installer, 'memberCount > 0'), 'Installer must skip People backfill when Membership has no members.');

$italian = (string) file_get_contents($root . '/component/admin/language/it-IT/com_decaromembership.ini');
$expect(str_contains($italian, 'COM_DECAROMEMBERSHIP_EXPORT="Esporta"'), 'Italian Export label is missing.');
$expect(str_contains($italian, 'COM_DECAROMEMBERSHIP_NEW="Nuovo"'), 'Italian New label is missing.');

$recordsTemplate = (string) file_get_contents($root . '/component/admin/tmpl/records/default.php');
$expect(!str_contains($recordsTemplate, "COM_DECAROMEMBERSHIP_NEW"), 'Records template must not duplicate the Joomla toolbar New action.');
$expect(!str_contains($recordsTemplate, "Text::_('JNEW')"), 'Records view must not expose the untranslated JNEW key.');

$viewFiles = [
    'Dashboard' => $root . '/component/admin/src/View/Dashboard/HtmlView.php',
    'Information' => $root . '/component/admin/src/View/Information/HtmlView.php',
    'Record' => $root . '/component/admin/src/View/Record/HtmlView.php',
    'Records' => $root . '/component/admin/src/View/Records/HtmlView.php',
];

$assetService = (string) file_get_contents($root . '/component/admin/src/Service/AdminAssetService.php');
foreach (['registerAndUseStyle(', "'com_decaromembership/css/admin.css'", 'registerAndUseScript(', "'com_decaromembership/js/admin.js'"] as $marker) {
    $expect(str_contains($assetService, $marker), "AdminAssetService missing {$marker}.");
}

foreach ($viewFiles as $viewName => $viewPath) {
    $viewSource = (string) file_get_contents($viewPath);
    $expect(
        str_contains($viewSource, 'AdminAssetService::useAssets'),
        "{$viewName} view must use the centralized Membership asset service."
    );
}

$recordView = (string) file_get_contents($viewFiles['Record']);
$recordsView = (string) file_get_contents($viewFiles['Records']);
$expect(str_contains($recordsView, "ToolbarHelper::addNew('record.add')"), 'Records view must expose New through the Joomla toolbar.');

foreach ([
    'component/admin/tmpl/dashboard/default.php',
    'component/admin/tmpl/records/default.php',
    'component/admin/tmpl/record/default.php',
    'component/admin/tmpl/information/default.php',
    'component/admin/tmpl/information/core.php',
] as $templatePath) {
    $template = (string) file_get_contents($root . '/' . $templatePath);
    $expect(!str_contains($template, '<link rel="stylesheet"'), "{$templatePath} must not manually inject CSS.");
    $expect(!str_contains($template, '<script src='), "{$templatePath} must not manually inject JS.");
}

$updateSql = $root . '/component/admin/sql/updates/mysql/1.6.0.sql';
$expect(is_file($updateSql), 'Membership 1.6.0 SQL update is missing.');
if (is_file($updateSql)) {
    $sql = (string) file_get_contents($updateSql);
    $expect(str_contains($sql, 'application_date'), '1.6.0 SQL must contain application_date.');
    $expect(str_contains($sql, '#__decaromembership_member_history'), '1.6.0 SQL must create member history.');
    $expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i', $sql), '1.6.0 SQL must be non-destructive.');
}

$source = '';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/component/admin/src', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
        $source .= "\n" . file_get_contents($file->getPathname());
    }
}
$expect(!str_contains($source, '#__xdecaropeople_'), 'Membership must not query People private tables.');
$expect(!preg_match('/People\\\\Administrator\\\\(?:Model|Table)\\\\/', $source), 'Membership must not import People private Model/Table classes.');
$expect(str_contains($source, 'getPeopleByUuids'), 'Membership must use the People batch provider.');
$expect(str_contains($source, 'membership.person_memberships'), 'Membership must expose the person-memberships capability.');
$expect(str_contains($source, 'MembershipEligibilityService'), 'Membership eligibility service is missing.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership {$version} release compatibility contract OK\n";
