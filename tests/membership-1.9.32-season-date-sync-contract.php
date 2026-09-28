<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    $root . '/component/admin/src/Service/DclCardService.php' => [
        'syncCompetitionSeasonDates(',
        'competition_season_dates_sync',
        '#__decaromembership_cards',
    ],
    $root . '/plugins/system/decaromembership/src/Extension/Decaromembership.php' => [
        'onXdecaroCompetitionSeasonDatesChanged',
        'syncCompetitionSeasonDates(',
    ],
    $root . '/package/pkg_decaromembership.xml' => [
        'group="system"',
        'plg_system_decaromembership.zip',
    ],
    $root . '/component/admin/src/Model/RecordModel.php' => [
        "Competitions is authoritative for validity dates of competition cards",
    ],
];

foreach ($checks as $file => $needles) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing file: {$file}\n");
        exit(1);
    }
    $contents = (string) file_get_contents($file);
    foreach ($needles as $needle) {
        if (strpos($contents, $needle) === false) {
            fwrite(STDERR, "Missing contract token '{$needle}' in {$file}\n");
            exit(1);
        }
    }
}

echo "Membership 1.9.32 season date sync contract OK\n";
