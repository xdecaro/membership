<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$model = file_get_contents($root . '/component/admin/src/Model/RecordsModel.php');
$dashboard = file_get_contents($root . '/component/admin/src/Model/DashboardModel.php');
$template = file_get_contents($root . '/component/admin/tmpl/records/default.php');

$failures = [];

$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$expect(str_contains($model, "filter.published"), 'RecordsModel must keep a published-state filter.');
$expect(str_contains($model, "getInt('published'"), 'RecordsModel must read the published-state filter from input.');
$expect(!str_contains($model, "$entity === 'members' ? $published : null"), 'Published-state filtering must not be restricted to members.');
$expect(str_contains($model, "a.published") && str_contains($model, "= :published"), 'RecordsModel must filter by the requested published state.');
$expect(str_contains($template, 'name="published"'), 'Publishable lists must render a published-state selector.');
$expect(!str_contains($template, "$this->entity==='members'?$app->input->getString('published','') : ''"), 'Published-state selector must not be restricted to members.');
$expect(str_contains($template, "JTRASHED"), 'Publishable lists must offer the trashed state.');
$expect(str_contains($dashboard, "published") && str_contains($dashboard, ">= 0"), 'Dashboard counts must exclude trashed records for entities with published state.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "All entity state filter contract OK\n";
