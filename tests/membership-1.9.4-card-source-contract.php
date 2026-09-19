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
$expect(version_compare($version, '1.9.4', '>='), 'VERSION must be 1.9.4 or newer.');

$config = (string) file_get_contents($root . '/component/admin/src/Config/MemberCoreEntities.php');
$repository = (string) file_get_contents($root . '/component/admin/src/Service/RecordRepository.php');
$view = (string) file_get_contents($root . '/component/admin/src/View/Record/HtmlView.php');
$template = (string) file_get_contents($root . '/component/admin/tmpl/record/default.php');
$install = (string) file_get_contents($root . '/component/admin/sql/install.mysql.utf8mb4.sql');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.4.sql');

$expect(!str_contains(
    $config,
    "'card_number'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CARD_NUMBER','type'=>'text','unique'=>true]"
), 'Member record must no longer expose an editable card_number field.');
$expect(str_contains($config, "'search'=>['first_name','last_name','tax_code','member_number','email']"), 'Member search must not treat legacy card_number as canonical.');
$expect(str_contains($config, "'list'=>['member_number','last_name','first_name','category_id','status','email']"), 'Member list must not expose the legacy card_number column.');

foreach (['loadCurrentMemberCard', '#__decaromembership_cards', "status') . \" = 'active'"] as $marker) {
    $expect(str_contains($repository, $marker), "RecordRepository current-card lookup missing {$marker}.");
}
foreach (['currentMemberCard', 'legacyCardNumber', 'loadCurrentMemberCard'] as $marker) {
    $expect(str_contains($view, $marker), "Member view current-card integration missing {$marker}.");
}
foreach ([
    'COM_DECAROMEMBERSHIP_MEMBER_CARD_SUMMARY',
    'COM_DECAROMEMBERSHIP_MEMBER_CARD_MANAGE',
    'COM_DECAROMEMBERSHIP_MEMBER_CARD_LEGACY',
    'entity=cards',
] as $marker) {
    $expect(str_contains($template, $marker), "Member card summary UX missing {$marker}.");
}

$expect(str_contains($install, '#__decaromembership_members') && str_contains($install, 'card_number'), 'Legacy member.card_number column must remain in the clean schema for compatibility.');
$expect(str_contains($install, '#__decaromembership_cards'), 'Cards table must remain present as the authoritative card store.');
$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b/i', $update), '1.9.4 migration marker must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.4 card source contract OK\n";
