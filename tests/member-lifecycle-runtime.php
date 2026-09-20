<?php

declare(strict_types=1);

$joomlaRoot = rtrim((string) getenv('MEMBERSHIP_JOOMLA_ROOT'), DIRECTORY_SEPARATOR);
if ($joomlaRoot === '' || !is_file($joomlaRoot . '/includes/defines.php')) {
    fwrite(STDERR, "MEMBERSHIP_JOOMLA_ROOT does not point to an installed Joomla site.\n");
    exit(1);
}

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['REQUEST_URI'] = '/administrator/index.php';
$_SERVER['SCRIPT_NAME'] = '/administrator/index.php';
$_SERVER['PHP_SELF'] = '/administrator/index.php';
$_SERVER['HTTPS'] = 'off';

define('_JEXEC', 1);
define('JPATH_BASE', $joomlaRoot);
require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$container = \Joomla\CMS\Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias('JSession', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');

$app = $container->get(\Joomla\Console\Application::class);
\Joomla\CMS\Factory::$application = $app;
$app->createExtensionNamespaceMap();

$userFactory = $container->get(\Joomla\CMS\User\UserFactoryInterface::class);
$admin = $userFactory->loadUserByUsername('admin');
if (!$admin || !$admin->authorise('core.admin')) {
    $fail('Cannot load Joomla CI super user.');
}
$app->loadIdentity($admin);

$db = $container->get(\Joomla\Database\DatabaseInterface::class);

$paramsJson = json_encode([
    'member_number_mode' => 'automatic',
    'member_number_prefix' => 'CI-',
    'member_number_padding' => 5,
    'member_default_status' => 'pending',
], JSON_UNESCAPED_SLASHES);
$query = $db->getQuery(true)
    ->update($db->quoteName('#__extensions'))
    ->set($db->quoteName('params') . ' = :params')
    ->where($db->quoteName('type') . " = 'component'")
    ->where($db->quoteName('element') . " = 'com_decaromembership'")
    ->bind(':params', $paramsJson);
$db->setQuery($query)->execute();

$componentRecord = \Joomla\CMS\Component\ComponentHelper::getComponent('com_decaromembership');
$componentRecord->setParams(new \Joomla\Registry\Registry($paramsJson));

$membership = $app->bootComponent('com_decaromembership');
$model = $membership->getMVCFactory()->createModel('Record', 'Administrator', ['ignore_request' => true]);
if (!is_object($model) || !method_exists($model, 'saveEntity')) {
    $fail('Membership Record model is unavailable.');
}

$category = (object) [
    'name' => 'CI Standard',
    'code' => 'CI-STANDARD',
    'description' => 'Lifecycle runtime category',
    'language' => '*',
    'ordering' => 1,
    'published' => 1,
    'created' => '2026-09-19 03:00:00',
    'created_by' => (int) $admin->id,
    'modified_by' => 0,
];
$db->insertObject('#__decaromembership_categories', $category);
$categoryId = (int) $db->insertid();
if ($categoryId < 1) {
    $fail('Cannot create Membership category.');
}

$people = [
    ['uuid' => '550e8400-e29b-41d4-a716-446655440191', 'display_name' => 'CI Lifecycle Member', 'email' => 'ci-lifecycle@example.invalid'],
    ['uuid' => '550e8400-e29b-41d4-a716-446655440192', 'display_name' => 'CI Missing Category', 'email' => 'ci-missing-category@example.invalid'],
    ['uuid' => '550e8400-e29b-41d4-a716-446655440193', 'display_name' => 'CI Legacy Blank Status', 'email' => 'ci-legacy-blank@example.invalid'],
];

foreach ($people as $row) {
    $person = (object) [
        'uuid' => $row['uuid'],
        'display_name' => $row['display_name'],
        'first_name' => 'CI',
        'last_name' => $row['display_name'],
        'email' => $row['email'],
        'person_status' => 'active',
        'state' => 1,
        'access' => 1,
        'created' => '2026-09-19 03:01:00',
        'created_by' => (int) $admin->id,
        'modified_by' => 0,
    ];
    $db->insertObject('#__xdecaropeople_people', $person);
}

$memberId = $model->saveEntity('members', 0, [
    'person_uuid' => $people[0]['uuid'],
    'category_id' => $categoryId,
    'status' => 'active',
    'organization_uuid' => '',
]);

$repository = new \Xdecaro\Component\Decaromembership\Administrator\Service\RecordRepository($db);
$member = $repository->load('#__decaromembership_members', $memberId);
if (!$member) {
    $fail('Created Membership member cannot be loaded.');
}

$expectedNumber = 'CI-' . str_pad((string) $memberId, 5, '0', STR_PAD_LEFT);
if ((string) $member->member_number !== $expectedNumber) {
    $fail('Automatic member number mismatch: expected ' . $expectedNumber . ', got ' . (string) $member->member_number);
}
if ((int) $member->category_id !== $categoryId) {
    $fail('Member category was not persisted.');
}
if ((string) $member->status !== 'active') {
    $fail('Member status was not persisted.');
}

if ((int) $member->published !== 1) {
    $fail('New member did not default to published=1.');
}

$db->setQuery(
    $db->getQuery(true)
        ->update($db->quoteName('#__decaromembership_members'))
        ->set($db->quoteName('published') . ' = 0')
        ->where($db->quoteName('id') . ' = ' . (int) $memberId)
)->execute();

$memberOptions = $model->getRelationOptions('members');
$memberOptionIds = array_map(static fn(object $row): int => (int) $row->id, $memberOptions);
if (!in_array($memberId, $memberOptionIds, true)) {
    $fail('Unpublished member disappeared from administrator relation options.');
}

$db->setQuery(
    $db->getQuery(true)
        ->update($db->quoteName('#__decaromembership_members'))
        ->set($db->quoteName('published') . ' = 1')
        ->where($db->quoteName('id') . ' = ' . (int) $memberId)
)->execute();

$renewalId = $model->saveEntity('renewals', 0, [
    'member_id' => $memberId,
    'association_year' => '2026',
]);
$renewal = $repository->load('#__decaromembership_renewals', $renewalId);
if (!$renewal) {
    $fail('Created renewal cannot be loaded.');
}
if ((string) $renewal->status !== 'due') {
    $fail('Renewal did not default to due.');
}
if ((string) $renewal->payment_status !== 'unpaid') {
    $fail('Renewal did not default to unpaid payment status.');
}

$today = \Joomla\CMS\Factory::getDate()->format('Y-m-d');
foreach (['first_registration_date','admission_date','current_membership_start_date','status_effective_date'] as $field) {
    if ((string) ($member->{$field} ?? '') !== $today) {
        $fail("Automatic lifecycle date {$field} was not set to {$today}.");
    }
}

$missingCardTypeRejected = false;
try {
    $model->saveEntity('cards', 0, [
        'member_id' => $memberId,
        'card_number' => 'CI-CARD-MISSING-TYPE-' . $memberId,
        'status' => 'pending',
        'published' => 1,
    ]);
} catch (\RuntimeException $e) {
    $missingCardTypeRejected = true;
}
if (!$missingCardTypeRejected) {
    $fail('Card creation without type must be rejected.');
}

$missingCardStatusRejected = false;
try {
    $model->saveEntity('cards', 0, [
        'member_id' => $memberId,
        'card_number' => 'CI-CARD-MISSING-STATUS-' . $memberId,
        'type' => 'physical',
        'published' => 1,
    ]);
} catch (\RuntimeException $e) {
    $missingCardStatusRejected = true;
}
if (!$missingCardStatusRejected) {
    $fail('Card creation without status must be rejected.');
}

$olderCard = (object) [
    'member_id' => $memberId,
    'card_number' => 'CI-CARD-OLD-' . $memberId,
    'type' => 'physical',
    'status' => 'replaced',
    'issued_at' => '2025-01-01',
    'published' => 1,
    'created' => '2026-09-19 03:03:00',
    'created_by' => (int) $admin->id,
    'modified_by' => 0,
];
$db->insertObject('#__decaromembership_cards', $olderCard);

$currentCardRow = (object) [
    'member_id' => $memberId,
    'card_number' => 'CI-CARD-ACTIVE-' . $memberId,
    'type' => 'electronic',
    'status' => 'active',
    'issued_at' => '2026-01-01',
    'activated_at' => '2026-01-02',
    'annual_mark' => '2026',
    'published' => 1,
    'created' => '2026-09-19 03:04:00',
    'created_by' => (int) $admin->id,
    'modified_by' => 0,
];
$db->insertObject('#__decaromembership_cards', $currentCardRow);

$currentCard = $repository->loadCurrentMemberCard($memberId);
if (!$currentCard || (string) $currentCard->card_number !== 'CI-CARD-ACTIVE-' . $memberId) {
    $fail('Current member card was not resolved from the Cards table.');
}
if ((string) $currentCard->status !== 'active') {
    $fail('Current member card lookup did not prefer the active card.');
}

$missingCategoryRejected = false;
try {
    $model->saveEntity('members', 0, [
        'person_uuid' => $people[1]['uuid'],
        'status' => 'pending',
        'organization_uuid' => '',
        'published' => 1,
    ]);
} catch (\RuntimeException $e) {
    $missingCategoryRejected = true;
}
if (!$missingCategoryRejected) {
    $fail('Member creation without category must be rejected.');
}

$legacy = (object) [
    'person_uuid' => $people[2]['uuid'],
    'category_id' => $categoryId,
    'status' => '',
    'published' => 1,
    'created' => '2026-09-19 03:02:00',
    'created_by' => (int) $admin->id,
    'modified_by' => 0,
];
$db->insertObject('#__decaromembership_members', $legacy);
$legacyId = (int) $db->insertid();
if ($legacyId < 1) {
    $fail('Cannot create legacy blank-status member.');
}

$model->saveEntity('members', $legacyId, [
    'person_uuid' => $people[2]['uuid'],
    'category_id' => $categoryId,
    'status' => 'active',
    'organization_uuid' => '',
    'published' => 1,
]);

$legacyUpdated = $repository->load('#__decaromembership_members', $legacyId);
if (!$legacyUpdated || (string) $legacyUpdated->status !== 'active') {
    $fail('Legacy blank-status member was not updated to active.');
}
foreach (['first_registration_date','admission_date','current_membership_start_date','status_effective_date'] as $field) {
    if (!empty($legacyUpdated->{$field})) {
        $fail("Legacy blank-status bootstrap invented lifecycle date {$field}.");
    }
}

$model->saveEntity('members', $memberId, [
    'person_uuid' => $people[0]['uuid'],
    'category_id' => $categoryId,
    'status' => 'resigned',
    'member_number' => $member->member_number,
    'first_registration_date' => $member->first_registration_date,
    'admission_date' => $member->admission_date,
    'current_membership_start_date' => $member->current_membership_start_date,
    'status_effective_date' => '',
    'cessation_date' => '',
    'organization_uuid' => '',
    'card_number' => 'SHOULD-NOT-BE-SAVED-ON-MEMBER',
    'published' => 1,
]);

$resigned = $repository->load('#__decaromembership_members', $memberId);
if (!$resigned || (string) $resigned->cessation_date !== $today) {
    $fail('Terminal membership status did not set cessation_date automatically.');
}
if ((string) $resigned->member_number !== $expectedNumber) {
    $fail('Automatic member number changed during update.');
}
if (trim((string) ($resigned->card_number ?? '')) !== '') {
    $fail('Member save must ignore legacy card_number input; Cards is authoritative.');
}


echo "Membership 1.9.0 member lifecycle runtime OK\n";
