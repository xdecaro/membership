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
$expect($version === '1.9.0', 'VERSION must be 1.9.0.');

$config = (string) file_get_contents($root . '/component/admin/src/Config/MemberCoreEntities.php');
$lifecycle = (string) file_get_contents($root . '/component/admin/src/Service/MemberLifecycleService.php');
$model = (string) file_get_contents($root . '/component/admin/src/Model/RecordModel.php');
$repository = (string) file_get_contents($root . '/component/admin/src/Service/RecordRepository.php');
$view = (string) file_get_contents($root . '/component/admin/src/View/Record/HtmlView.php');
$template = (string) file_get_contents($root . '/component/admin/tmpl/record/default.php');
$configXml = (string) file_get_contents($root . '/component/admin/config.xml');
$manifest = (string) file_get_contents($root . '/component/decaromembership.xml');
$install = str_replace(chr(96), '', (string) file_get_contents($root . '/component/admin/sql/install.mysql.utf8mb4.sql'));
$update = str_replace(chr(96), '', (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.0.sql'));

foreach ([
    "'status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_STATUS','type'=>'select','required'=>true,'default'=>'pending'",
    "'category_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CATEGORY','type'=>'relation','relation'=>'categories','required'=>true]",
    "'code'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CODE','type'=>'text','unique'=>true]",
] as $marker) {
    $expect(str_contains($config, $marker), "Member/category config missing {$marker}.");
}

foreach (['member_number_mode','member_number_padding','member_default_status','default="manual"','value="automatic"','value="manual"'] as $marker) {
    $expect(str_contains($configXml, $marker), "Membership options missing {$marker}.");
}

foreach (['prepareForSave','isAutomaticNumbering','generateNumber','TERMINAL_STATUSES','ACTIVE_STATUSES','first_registration_date','status_effective_date','cessation_date'] as $marker) {
    $expect(str_contains($lifecycle, $marker), "MemberLifecycleService missing {$marker}.");
}

foreach (['MemberLifecycleService','prepareForSave','generateNumber','updateMemberNumber','COM_DECAROMEMBERSHIP_ERROR_MEMBER_NUMBER_COLLISION'] as $marker) {
    $expect(str_contains($model, $marker), "RecordModel lifecycle integration missing {$marker}.");
}
$expect(str_contains($repository, 'updateMemberNumber'), 'RecordRepository must persist generated member numbers.');

foreach (['memberNumberAutomatic','memberNumberPadding','memberDefaultStatus','hasMemberCategories'] as $marker) {
    $expect(str_contains($view, $marker), "Member form view missing {$marker}.");
}

foreach (['COM_DECAROMEMBERSHIP_MEMBER_CATEGORY_MISSING','COM_DECAROMEMBERSHIP_MEMBER_CATEGORY_MANAGE','COM_DECAROMEMBERSHIP_MEMBER_NUMBER_AUTOMATIC_PLACEHOLDER','COM_DECAROMEMBERSHIP_FIRST_REGISTRATION_HELP'] as $marker) {
    $expect(str_contains($template, $marker), "Member form UX missing {$marker}.");
}

$expect(str_contains($manifest, 'entity=categories'), 'Categories must be directly accessible from the Membership submenu.');
foreach (['code VARCHAR(100) NULL', 'uq_category_code'] as $marker) {
    $expect(str_contains($install, $marker), "Clean schema missing {$marker}.");
    $expect(str_contains($update, $marker), "1.9.0 migration missing {$marker}.");
}
$expect(!preg_match('/\\b(?:DROP\\s+TABLE|TRUNCATE\\s+TABLE)\\b/i', $update), '1.9.0 migration must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.0 member lifecycle basics contract OK\\n";
