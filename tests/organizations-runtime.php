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
$membership = $app->bootComponent('com_decaromembership');
if (!is_object($membership) || !method_exists($membership, 'getOrganizationsIntegrationService')) {
    $fail('Membership Organizations integration service is unavailable.');
}

$organizations = $membership->getOrganizationsIntegrationService();
if (!$organizations->isAvailable()) {
    $fail('Organizations public provider should be available in the integration runtime.');
}

$organizationUuid = '550e8400-e29b-41d4-a716-446655440110';
$organizationRow = (object) [
    'uuid' => $organizationUuid,
    'name' => 'CI Optional Organization',
    'code' => 'CI-ORG',
    'type' => 'provincial_section',
    'state' => 1,
    'access' => 1,
    'created' => '2026-09-18 12:00:00',
    'created_by' => (int) $admin->id,
    'modified_by' => 0,
];
$db->insertObject('#__xdecaroorganizations_organizations', $organizationRow);

$hierarchyRows = [
    [
        'uuid' => '550e8400-e29b-41d4-a716-446655440120',
        'name' => 'ENS',
        'code' => 'ENS',
        'type' => 'association',
        'parent_id' => null,
    ],
    [
        'uuid' => '550e8400-e29b-41d4-a716-446655440121',
        'name' => 'ENS Lazio',
        'code' => 'ENS-LAZIO',
        'type' => 'association',
        'parent_id' => '__ROOT__',
    ],
    [
        'uuid' => '550e8400-e29b-41d4-a716-446655440122',
        'name' => 'ENS Roma',
        'code' => 'ENS-ROMA',
        'type' => 'association',
        'parent_id' => '__REGION__',
    ],
];

$rootId = 0;
$regionId = 0;
foreach ($hierarchyRows as $index => $row) {
    if ($row['parent_id'] === '__ROOT__') {
        $row['parent_id'] = $rootId;
    } elseif ($row['parent_id'] === '__REGION__') {
        $row['parent_id'] = $regionId;
    }

    $object = (object) array_merge($row, [
        'state' => 1,
        'access' => 1,
        'created' => '2026-09-19 02:00:00',
        'created_by' => (int) $admin->id,
        'modified_by' => 0,
    ]);
    $db->insertObject('#__xdecaroorganizations_organizations', $object);
    $insertedId = (int) $db->insertid();

    if ($index === 0) {
        $rootId = $insertedId;
    } elseif ($index === 1) {
        $regionId = $insertedId;
    }
}

$hierarchy = [];
foreach ($organizations->searchOrganizations('ENS', 20) as $organization) {
    $hierarchy[(string) ($organization['name'] ?? '')] = $organization;
}

if ((int) ($hierarchy['ENS Lazio']['parent_id'] ?? 0) !== $rootId) {
    $fail('Organizations provider did not expose the ENS Lazio parent relationship.');
}
if ((int) ($hierarchy['ENS Roma']['parent_id'] ?? 0) !== $regionId) {
    $fail('Organizations provider did not expose the ENS Roma parent relationship.');
}

$found = $organizations->getOrganization($organizationUuid);
if (($found['name'] ?? '') !== 'CI Optional Organization') {
    $fail('Membership cannot resolve an Organizations record through the public provider.');
}
if ($organizations->validateOptionalUuid(strtoupper($organizationUuid)) !== $organizationUuid) {
    $fail('Membership did not normalize/validate the Organizations UUID.');
}

$peopleRows = [
    [
        'uuid' => '550e8400-e29b-41d4-a716-446655440111',
        'display_name' => 'CI Standalone Member',
        'first_name' => 'CI Standalone',
        'last_name' => 'Member',
        'email' => 'ci-standalone@example.invalid',
    ],
    [
        'uuid' => '550e8400-e29b-41d4-a716-446655440112',
        'display_name' => 'CI Organization Member',
        'first_name' => 'CI Organization',
        'last_name' => 'Member',
        'email' => 'ci-organization@example.invalid',
    ],
];

foreach ($peopleRows as $row) {
    $person = (object) array_merge($row, [
        'person_status' => 'active',
        'state' => 1,
        'access' => 1,
        'created' => '2026-09-18 12:01:00',
        'created_by' => (int) $admin->id,
        'modified_by' => 0,
    ]);
    $db->insertObject('#__xdecaropeople_people', $person);
}

$model = $membership->getMVCFactory()->createModel('Record', 'Administrator', ['ignore_request' => true]);
if (!is_object($model) || !method_exists($model, 'saveEntity')) {
    $fail('Membership Record model is unavailable.');
}

$category = (object) [
    'name' => 'CI Organizations Member',
    'code' => 'CI-ORG-MEMBER',
    'language' => '*',
    'ordering' => 1,
    'published' => 1,
    'created' => '2026-09-19 02:10:00',
    'created_by' => (int) $admin->id,
    'modified_by' => 0,
];
$db->insertObject('#__decaromembership_categories', $category);
$categoryId = (int) $db->insertid();

$standaloneId = $model->saveEntity('members', 0, [
    'person_uuid' => $peopleRows[0]['uuid'],
    'category_id' => $categoryId,
    'member_number' => 'CI-SIMPLE-001',
    'status' => 'active',
    'first_registration_date' => '2026-09-18',
    'organization_uuid' => '',
    'published' => 1,
]);
$repository = new \Xdecaro\Component\Decaromembership\Administrator\Service\RecordRepository($db);
$standalone = $repository->load('#__decaromembership_members', $standaloneId);
if (!$standalone || $standalone->organization_uuid !== null) {
    $fail('A member without Organizations must save with a null organization_uuid.');
}

$linkedId = $model->saveEntity('members', 0, [
    'person_uuid' => $peopleRows[1]['uuid'],
    'category_id' => $categoryId,
    'member_number' => 'CI-ORG-001',
    'status' => 'active',
    'first_registration_date' => '2026-09-18',
    'organization_uuid' => $organizationUuid,
    'published' => 1,
]);
$linked = $repository->load('#__decaromembership_members', $linkedId);
if (!$linked || strtolower((string) $linked->organization_uuid) !== $organizationUuid) {
    $fail('A member linked to Organizations did not persist the organization UUID.');
}

$personMemberships = $membership->getPersonMembershipService()->getMembershipsByPersonUuid($peopleRows[1]['uuid']);
if (($personMemberships[0]['organization_name'] ?? '') !== 'CI Optional Organization') {
    $fail('Public person Membership service did not resolve the organization name.');
}

$historyQuery = $db->getQuery(true)
    ->select(['event_type', 'new_organization_uuid'])
    ->from($db->quoteName('#__decaromembership_member_history'))
    ->where($db->quoteName('member_id') . ' = :member_id')
    ->bind(':member_id', $linkedId, \Joomla\Database\ParameterType::INTEGER);
$history = $db->setQuery($historyQuery, 0, 1)->loadAssoc();
if (!$history || strtolower((string) ($history['new_organization_uuid'] ?? '')) !== $organizationUuid) {
    $fail('Membership history did not preserve the Organizations link.');
}

$transferDestinationUuid = '550e8400-e29b-41d4-a716-446655440122';
$today = \Joomla\CMS\Factory::getDate()->format('Y-m-d');

$transferId = $model->saveEntity('transfers', 0, [
    'reference' => 'CI-TRANSFER-001',
    'member_id' => $linkedId,
    'to_organization_uuid' => $transferDestinationUuid,
    'status' => 'requested',
    'delegation_status' => 'unchecked',
    'sticker_status' => 'unchecked',
    'published' => 1,
]);

$transfer = $repository->load('#__decaromembership_transfers', $transferId);
if (!$transfer) {
    $fail('Organizations-based transfer was not created.');
}
if (strtolower((string) ($transfer->from_organization_uuid ?? '')) !== $organizationUuid) {
    $fail('Transfer source organization was not derived from the member organization.');
}
if (strtolower((string) ($transfer->to_organization_uuid ?? '')) !== $transferDestinationUuid) {
    $fail('Transfer destination organization was not persisted.');
}
if ((string) ($transfer->requested_at ?? '') !== $today) {
    $fail('New transfer did not default Data richiesta to today.');
}

$incompleteRejected = false;
try {
    $model->saveEntity('transfers', $transferId, [
        'reference' => 'CI-TRANSFER-001',
        'member_id' => $linkedId,
        'from_organization_uuid' => $organizationUuid,
        'to_organization_uuid' => $transferDestinationUuid,
        'requested_at' => $today,
        'effective_at' => $today,
        'status' => 'completed',
        'delegation_status' => 'unchecked',
        'card_position' => '',
        'sticker_status' => 'unchecked',
        'arrears_amount' => 0,
        'source_confirmed' => 1,
        'destination_confirmed' => 1,
        'published' => 1,
    ]);
} catch (\RuntimeException) {
    $incompleteRejected = true;
}
if (!$incompleteRejected) {
    $fail('Transfer completed without delegation/card/sticker checks must be rejected.');
}

$model->saveEntity('transfers', $transferId, [
    'reference' => 'CI-TRANSFER-001',
    'member_id' => $linkedId,
    'from_organization_uuid' => $organizationUuid,
    'to_organization_uuid' => $transferDestinationUuid,
    'requested_at' => $today,
    'effective_at' => $today,
    'status' => 'completed',
    'delegation_status' => 'confirmed',
    'card_position' => 'Verified',
    'sticker_status' => 'active',
    'arrears_amount' => 0,
    'source_confirmed' => 1,
    'destination_confirmed' => 1,
    'completed_at' => '',
    'published' => 1,
]);

$completedTransfer = $repository->load('#__decaromembership_transfers', $transferId);
if (!$completedTransfer || (string) ($completedTransfer->completed_at ?? '') !== $today) {
    $fail('Completed transfer did not default Data completamento to today.');
}

$transferredMember = $repository->load('#__decaromembership_members', $linkedId);
if (!$transferredMember || strtolower((string) ($transferredMember->organization_uuid ?? '')) !== $transferDestinationUuid) {
    $fail('Completed transfer did not update member organization.');
}

echo "Membership optional Organizations and transfer runtime contract OK\n";
