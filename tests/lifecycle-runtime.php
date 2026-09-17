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
if (!is_object($membership)
    || !method_exists($membership, 'getMembershipEligibilityService')
    || !method_exists($membership, 'getMembershipPersonHistoryService')) {
    $fail('Membership lifecycle public services are unavailable.');
}

$eligibility = $membership->getMembershipEligibilityService();
$history = $membership->getMembershipPersonHistoryService();

$personUuid = '650e8400-e29b-41d4-a716-446655440099';
$person = (object) [
    'uuid' => $personUuid,
    'display_name' => 'Lifecycle Person',
    'first_name' => 'Lifecycle',
    'last_name' => 'Person',
    'person_status' => 'active',
    'state' => 1,
    'access' => 1,
    'created' => '2026-09-17 20:00:00',
    'created_by' => (int) $admin->id,
    'modified_by' => 0,
];
$db->insertObject('#__xdecaropeople_people', $person);

$category = (object) ['name' => 'Lifecycle Category', 'published' => 1, 'created' => '2026-09-17 20:00:00', 'created_by' => (int) $admin->id, 'modified_by' => 0];
$db->insertObject('#__decaromembership_categories', $category, 'id');
$location = (object) ['name' => 'Lifecycle Location', 'code' => 'CI-LIFE', 'published' => 1, 'created' => '2026-09-17 20:00:00', 'created_by' => (int) $admin->id, 'modified_by' => 0];
$db->insertObject('#__decaromembership_locations', $location, 'id');

$member = (object) [
    'person_uuid' => $personUuid,
    'member_number' => 'CI-LIFE-001',
    'first_registration_date' => '2020-01-01',
    'current_period_started_on' => '2026-01-01',
    'status' => 'active',
    'rights_status' => 'normal',
    'seniority_credit_days' => 30,
    'location_id' => (int) $location->id,
    'category_id' => (int) $category->id,
    'published' => 1,
    'created' => '2026-09-17 20:00:00',
    'created_by' => (int) $admin->id,
    'modified_by' => 0,
];
$db->insertObject('#__decaromembership_members', $member, 'id');
if ((int) $member->id < 1) $fail('Cannot seed lifecycle member.');

$history->recordTransition((int) $member->id, null, $member, (int) $admin->id, '2026-09-17 20:00:00');

$renewal = (object) [
    'member_id' => (int) $member->id,
    'association_year' => '2026',
    'status' => 'completed',
    'payment_status' => 'paid',
    'published' => 1,
    'created' => '2026-09-17 20:01:00',
    'created_by' => (int) $admin->id,
    'modified_by' => 0,
];
$db->insertObject('#__decaromembership_renewals', $renewal);

if (!$eligibility->isActiveMember((int) $member->id)) $fail('Active member eligibility failed.');
if (!$eligibility->isFeeCurrent((int) $member->id, '2026')) $fail('Current fee eligibility failed.');
if (!$eligibility->canVote((int) $member->id)) $fail('Default voting eligibility failed.');
if (!$eligibility->canBeCandidate((int) $member->id)) $fail('Default candidacy eligibility failed.');
if ($eligibility->getMembershipSeniorityDays((int) $member->id) < 30) $fail('Membership seniority calculation failed.');

$old = clone $member;
$member->status = 'suspended';
$member->rights_status = 'suspended';
$member->status_reason = 'CI lifecycle test';
$db->updateObject('#__decaromembership_members', $member, 'id', true);
$history->recordTransition((int) $member->id, $old, $member, (int) $admin->id, '2026-09-17 20:02:00');

if ($eligibility->canVote((int) $member->id) || $eligibility->canBeCandidate((int) $member->id)) {
    $fail('Suspended member retained association rights.');
}

$payload = $history->getHistoryByPersonUuid($personUuid);
if (($payload['current']['status'] ?? '') !== 'suspended') $fail('Public person history did not return current Membership status.');
if (($payload['current']['category_name'] ?? '') !== 'Lifecycle Category') $fail('Public person history did not resolve category.');
if (($payload['current']['location_name'] ?? '') !== 'Lifecycle Location') $fail('Public person history did not resolve location.');
if (count($payload['history'] ?? []) !== 2) $fail('Membership lifecycle history does not contain create and status events.');
if (($payload['eligibility']['can_vote'] ?? true) !== false) $fail('Public person history eligibility summary is inconsistent.');

$core = $membership->getCoreIntegrationService();
$capabilities = $core->getCapabilities();
$found = false;
foreach ($capabilities as $capability) {
    if (method_exists($capability, 'getName') && $capability->getName() === 'membership.people_history') {
        $found = true;
        break;
    }
    if ((string) ($capability->name ?? '') === 'membership.people_history') {
        $found = true;
        break;
    }
}
if (!$found && !str_contains((string) file_get_contents($joomlaRoot . '/administrator/components/com_decaromembership/src/Service/CoreIntegrationService.php'), 'membership.people_history')) {
    $fail('Membership people-history capability is missing.');
}

echo "Membership 1.6.0 lifecycle runtime contract OK\n";
