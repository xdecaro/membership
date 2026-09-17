<?php

declare(strict_types=1);
defined('_JEXEC') || define('_JEXEC', 1);

$root = dirname(__DIR__);
$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$version = trim((string) file_get_contents($root . '/VERSION'));
$expect($version === '1.5.0', 'VERSION must be 1.5.0.');

foreach ([
    'component/decaromembership.xml',
    'package/pkg_decaromembership.xml',
    'plugins/xdecaroanalytics/decaromembership/decaromembership.xml',
    'plugins/task/decaromembership/decaromembership.xml',
] as $manifestPath) {
    $xml = simplexml_load_file($root . '/' . $manifestPath);
    $expect($xml !== false && (string) $xml->version === '1.5.0', "{$manifestPath} must be 1.5.0.");
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
$expect(($assets['version'] ?? '') === '1.5.0', 'Web Asset version must be 1.5.0.');

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

foreach ($viewFiles as $viewName => $viewPath) {
    $viewSource = (string) file_get_contents($viewPath);
    $expect(
        str_contains($viewSource, '$this->getDocument()->getWebAssetManager()'),
        "{$viewName} view must use the document injected into the Joomla 6 view."
    );
    foreach (['registerAndUseStyle(', "'com_decaromembership.admin'", "'com_decaromembership/css/admin.css'"] as $marker) {
        $expect(
            str_contains($viewSource, $marker),
            "{$viewName} view must directly register and use the Membership admin stylesheet."
        );
    }
    $expect(
        !str_contains($viewSource, 'getApplication()->getDocument()->getWebAssetManager()'),
        "{$viewName} view must not register assets through the application-global document."
    );
}

$recordView = (string) file_get_contents($viewFiles['Record']);
$recordsView = (string) file_get_contents($viewFiles['Records']);
$expect(str_contains($recordsView, "ToolbarHelper::addNew('record.add')"), 'Records view must expose New through the Joomla toolbar.');
foreach (['Record' => $recordView, 'Records' => $recordsView] as $viewName => $viewSource) {
    foreach (['registerAndUseScript(', "'com_decaromembership.admin'", "'com_decaromembership/js/admin.js'"] as $marker) {
        $expect(
            str_contains($viewSource, $marker),
            "{$viewName} view must directly register and use the Membership admin script."
        );
    }
}

$updateSql = $root . '/component/admin/sql/updates/mysql/1.5.0.sql';
$expect(is_file($updateSql), 'Membership 1.5.0 SQL update is missing.');
if (is_file($updateSql)) {
    $sql = (string) file_get_contents($updateSql);
    $expect(str_contains($sql, 'person_uuid'), '1.5.0 SQL must contain person_uuid.');
    $expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i', $sql), '1.5.0 SQL must be non-destructive.');
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

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.5.0 release contract OK\n";
