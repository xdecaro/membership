<?php

declare(strict_types=1);

$joomlaRoot = rtrim((string) getenv('MEMBERSHIP_JOOMLA_ROOT'), DIRECTORY_SEPARATOR);
$mode = strtolower(trim((string) (getenv('MEMBERSHIP_PEOPLE_RUNTIME_MODE') ?: 'clean')));

if ($joomlaRoot === '' || !is_file($joomlaRoot . '/includes/defines.php')) {
    fwrite(STDERR, "MEMBERSHIP_JOOMLA_ROOT does not point to an installed Joomla site.\n");
    exit(1);
}
if (!in_array($mode, ['clean', 'upgrade'], true)) {
    fwrite(STDERR, "Unknown People runtime mode.\n");
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
$membership = $app->bootComponent('com_decaromembership');
if (!is_object($membership) || !method_exists($membership, 'getPeopleIntegrationService')) {
    $fail('Membership People integration service is unavailable.');
}
$people = $membership->getPeopleIntegrationService();
$status = $people->dependencyStatus();
if (($status['core_version'] ?? null) !== '2.0.1' || ($status['people_version'] ?? null) !== '1.2.15' || !$people->isAvailable()) {
    $fail('Pinned Core/People dependencies are not available to Membership.');
}

if ($mode === 'clean') {
    $uuid = '550e8400-e29b-41d4-a716-446655440001';
    $personRow = (object) [
        'uuid' => $uuid,
        'display_name' => 'CI People Person',
        'first_name' => 'CI People',
        'last_name' => 'Person',
        'email' => 'ci-people@example.invalid',
        'person_status' => 'active',
        'state' => 1,
        'access' => 1,
        'created' => '2026-09-15 18:00:00',
        'created_by' => (int) $admin->id,
        'modified_by' => 0,
    ];
    $db->insertObject('#__xdecaropeople_people', $personRow);
    $personId = (int) $db->insertid();
    if ($personId < 1) {
        $fail('Cannot seed People person.');
    }

    $person = $people->getPerson($uuid, false);
    if (($person['display_name'] ?? '') !== 'CI People Person') {
        $fail('Membership cannot resolve the seeded People person.');
    }

    $repository = new \Xdecaro\Component\Decaromembership\Administrator\Service\RecordRepository($db);
    $audit = new \Xdecaro\Component\Decaromembership\Administrator\Service\AuditService($db);
    $linker = new \Xdecaro\Component\Decaromembership\Administrator\Service\MemberPeopleLinkService($repository, $people, $audit);
    $data = $linker->validateForSave(0, null, [
        'person_uuid' => strtoupper($uuid),
        'first_name' => 'SHOULD NOT COPY',
        'last_name' => 'SHOULD NOT COPY',
        'email' => 'legacy-copy@example.invalid',
        'member_number' => 'CI-PEOPLE-001',
        'status' => 'active',
        'published' => 1,
    ]);
    if (isset($data['first_name']) || isset($data['last_name']) || isset($data['email'])) {
        $fail('People-owned identity fields were copied into a new Membership member.');
    }
    $data['created'] = '2026-09-15 18:01:00';
    $data['created_by'] = (int) $admin->id;
    $data['modified'] = '2026-09-15 18:01:00';
    $data['modified_by'] = (int) $admin->id;
    $memberId = $repository->save('#__decaromembership_members', 0, $data);
    $member = $repository->load('#__decaromembership_members', $memberId);
    if ($member === null || strtolower((string) $member->person_uuid) !== $uuid || $member->first_name !== null || $member->last_name !== null) {
        $fail('Clean-install Membership member does not keep People as the identity source.');
    }

    $model = $membership->getMVCFactory()->createModel('Records', 'Administrator', ['ignore_request' => true]);
    if (!is_object($model) || !method_exists($model, 'resolvePeopleForItems')) {
        $fail('Membership member list model is unavailable.');
    }
    $resolved = $model->resolvePeopleForItems([$member]);
    if (($resolved[$uuid]['display_name'] ?? '') !== 'CI People Person') {
        $fail('Membership list batch resolution did not return the People identity.');
    }

    $db->updateObject('#__xdecaropeople_people', (object) [
        'id' => $personId,
        'display_name' => 'CI People Renamed',
        'first_name' => 'CI People',
        'last_name' => 'Renamed',
    ], 'id');
    $renamed = $people->getPerson($uuid, false);
    $memberAfterRename = $repository->load('#__decaromembership_members', $memberId);
    if (($renamed['display_name'] ?? '') !== 'CI People Renamed' || $memberAfterRename?->first_name !== null || $memberAfterRename?->last_name !== null) {
        $fail('People rename was not reflected dynamically without rewriting the Membership identity row.');
    }

    echo "Membership + People clean runtime contract OK\n";
    exit(0);
}

$uuid = strtolower((string) (getenv('MEMBERSHIP_EXPECTED_PERSON_UUID') ?: '550e8400-e29b-41d4-a716-446655440042'));
$memberNumber = (string) (getenv('MEMBERSHIP_EXPECTED_MEMBER_NUMBER') ?: 'CI-UPGRADE-001');
$repository = new \Xdecaro\Component\Decaromembership\Administrator\Service\RecordRepository($db);
$backfill = new \Xdecaro\Component\Decaromembership\Administrator\Service\MemberPeopleBackfillService(
    $repository,
    $people,
    new \Xdecaro\Component\Decaromembership\Administrator\Service\AuditService($db)
);
$first = $backfill->run((int) $admin->id, '2026-09-15 18:29:00');
if (($first['linked'] ?? -1) !== 1) {
    $fail('Authenticated People backfill did not link exactly one deterministic legacy member.');
}

$query = $db->getQuery(true)
    ->select('*')
    ->from($db->quoteName('#__decaromembership_members'))
    ->where($db->quoteName('member_number') . ' = :member_number')
    ->bind(':member_number', $memberNumber);
$member = $db->setQuery($query, 0, 1)->loadObject();
if (!$member || strtolower((string) ($member->person_uuid ?? '')) !== $uuid) {
    $fail('1.4.0 legacy member was not backfilled to the unique People UUID.');
}
if ((string) $member->first_name !== 'Legacy' || (string) $member->last_name !== 'Member') {
    $fail('1.4.0 legacy identity values were not preserved during upgrade.');
}

foreach (['renewals', 'cards', 'dues', 'payments', 'transfers'] as $table) {
    $countQuery = $db->getQuery(true)
        ->select('COUNT(*)')
        ->from($db->quoteName('#__decaromembership_' . $table))
        ->where($db->quoteName('member_id') . ' = :member_id')
        ->bind(':member_id', $member->id, \Joomla\Database\ParameterType::INTEGER);
    if ((int) $db->setQuery($countQuery)->loadResult() !== 1) {
        $fail('Upgrade did not preserve Membership child table: ' . $table);
    }
}

$auditQuery = $db->getQuery(true)
    ->select('COUNT(*)')
    ->from($db->quoteName('#__decaromembership_audit_log'))
    ->where($db->quoteName('entity_type') . " = 'members'")
    ->where($db->quoteName('entity_id') . ' = :member_id')
    ->where($db->quoteName('action') . " = 'people_backfill'")
    ->bind(':member_id', $member->id, \Joomla\Database\ParameterType::INTEGER);
if ((int) $db->setQuery($auditQuery)->loadResult() !== 1) {
    $fail('Upgrade did not record exactly one People backfill audit event.');
}

$person = $people->getPerson($uuid, false);
if (($person['display_name'] ?? '') !== 'Upgrade People Person') {
    $fail('Backfilled People identity cannot be resolved through the public provider.');
}

$second = $backfill->run((int) $admin->id, '2026-09-15 18:30:00');
if (($second['linked'] ?? -1) !== 0) {
    $fail('People backfill is not idempotent on the second run.');
}

$memberAfter = $repository->load('#__decaromembership_members', (int) $member->id);
if (!$memberAfter || strtolower((string) $memberAfter->person_uuid) !== $uuid || (string) $memberAfter->first_name !== 'Legacy' || (string) $memberAfter->last_name !== 'Member') {
    $fail('Second People backfill changed the upgraded Membership member.');
}

echo "Membership 1.4.0 -> 1.5.0 People upgrade runtime contract OK\n";
