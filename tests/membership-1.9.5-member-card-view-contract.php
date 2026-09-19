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
$expect(version_compare($version, '1.9.5', '>='), 'VERSION must be 1.9.5 or newer.');

$model = (string) file_get_contents($root . '/component/admin/src/Model/RecordModel.php');
$view = (string) file_get_contents($root . '/component/admin/src/View/Record/HtmlView.php');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.5.sql');

$expect(str_contains($model, 'public function getCurrentMemberCard(int $memberId): ?object'), 'RecordModel must expose getCurrentMemberCard().');
$expect(str_contains($model, 'return $this->repository()->loadCurrentMemberCard($memberId);'), 'RecordModel current-card method must delegate to the repository.');
$expect(str_contains($view, '$model->getCurrentMemberCard($memberId)'), 'HtmlView must retrieve the current card through RecordModel.');
$expect(!str_contains($view, '$this->getDatabase()'), 'HtmlView must not call undefined getDatabase().');
$expect(!str_contains($view, 'new RecordRepository('), 'HtmlView must not construct RecordRepository directly.');
$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b/i', $update), '1.9.5 migration marker must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.5 member card view contract OK\n";
