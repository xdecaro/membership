<?php

declare(strict_types=1);

$joomlaRoot = rtrim((string) getenv('MEMBERSHIP_JOOMLA_ROOT'), DIRECTORY_SEPARATOR);
if ($joomlaRoot === '' || !is_file($joomlaRoot . '/includes/defines.php')) {
    fwrite(STDERR, "MEMBERSHIP_JOOMLA_ROOT does not point to an installed Joomla site.\n");
    exit(1);
}

// Membership's MVCFactory can resolve Joomla's router even from a CLI probe.
// Give that dependency a deterministic valid URI instead of PHP's absolute
// CLI SCRIPT_NAME, which Joomla would otherwise parse as http:///....
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['REQUEST_URI'] = '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['HTTPS'] = 'off';

define('_JEXEC', 1);
define('JPATH_BASE', $joomlaRoot);
require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';

$container = \Joomla\CMS\Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias('JSession', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
$app = $container->get(\Joomla\Console\Application::class);
\Joomla\CMS\Factory::$application = $app;
$app->createExtensionNamespaceMap();

$membershipComponent = $app->bootComponent('com_decaromembership');
if (!is_object($membershipComponent) || !method_exists($membershipComponent, 'getCrossProductIntegrationService')) {
    fwrite(STDERR, "Membership public integration service unavailable.\n");
    exit(1);
}
$bridge = $membershipComponent->getCrossProductIntegrationService();
if (!$bridge->financeAvailable()) {
    fwrite(STDERR, "Finance 1.3.0 is not available to Membership.\n");
    exit(1);
}

$financeComponent = $app->bootComponent('com_decarofinance');
if (!is_object($financeComponent) || !method_exists($financeComponent, 'getFinanceService')) {
    fwrite(STDERR, "Finance public component service unavailable.\n");
    exit(1);
}
$finance = $financeComponent->getFinanceService();
$db = $container->get(\Joomla\Database\DatabaseInterface::class);

$insertRow = static function (string $table, array $data) use ($db): int {
    $row = (object) $data;
    $db->insertObject($table, $row);
    return (int) $db->insertid();
};
$updateRow = static function (string $table, array $data, string $key = 'id') use ($db): void {
    $row = (object) $data;
    $db->updateObject($table, $row, $key);
};

$memberId = $insertRow('#__decaromembership_members', [
    'first_name' => 'CI',
    'last_name' => 'Finance Bridge',
    'status' => 'active',
    'published' => 1,
    'created' => '2026-09-09 12:00:00',
    'created_by' => 1,
]);
if ($memberId < 1) {
    fwrite(STDERR, "Cannot create Membership CI member.\n");
    exit(1);
}

$dueId = $insertRow('#__decaromembership_dues', [
    'member_id' => $memberId,
    'association_year' => '2026',
    'amount' => '80.00',
    'paid_amount' => '0.00',
    'status' => 'unpaid',
    'due_date' => '2026-12-31',
    'published' => 1,
    'created' => '2026-09-09 12:00:00',
    'created_by' => 1,
]);

$paymentId = $insertRow('#__decaromembership_payments', [
    'member_id' => $memberId,
    'due_id' => $dueId,
    'amount' => '80.00',
    'method' => 'bank_transfer',
    'status' => 'paid',
    'paid_at' => '2026-09-09',
    'reference' => 'CI-MEMBERSHIP-FINANCE-' . $memberId,
    'published' => 1,
    'created' => '2026-09-09 12:00:00',
    'created_by' => 1,
]);
if ($dueId < 1 || $paymentId < 1) {
    fwrite(STDERR, "Cannot create Membership CI due/payment.\n");
    exit(1);
}

// A due already synchronized but not allocated must accept the latest local amount.
$obligationId1 = $bridge->syncDueToFinance($dueId, 'EUR', 1);
if (!is_int($obligationId1) || $obligationId1 < 1) {
    fwrite(STDERR, "Initial Membership due synchronization failed.\n");
    exit(1);
}
$updateRow('#__decaromembership_dues', ['id' => $dueId, 'amount' => '90.00']);
$obligationId2 = $bridge->syncDueToFinance($dueId, 'EUR', 1);
$obligation = $finance->getObligation((int) $obligationId2);
if ($obligationId1 !== $obligationId2 || !is_array($obligation) || abs((float) ($obligation['amount'] ?? 0) - 90.0) > 0.0001) {
    fwrite(STDERR, "Updated Membership due was not propagated before allocation.\n");
    exit(1);
}

// A payment already synchronized but not allocated must accept the latest local amount.
$financePaymentId1 = $bridge->syncPaymentToFinance($paymentId, 'EUR', 1);
if (!is_int($financePaymentId1) || $financePaymentId1 < 1) {
    fwrite(STDERR, "Initial Membership payment synchronization failed.\n");
    exit(1);
}
$updateRow('#__decaromembership_payments', ['id' => $paymentId, 'amount' => '90.00']);
$financePaymentId2 = $bridge->syncPaymentToFinance($paymentId, 'EUR', 1);
$financePayment = $finance->getPayment((int) $financePaymentId2);
if ($financePaymentId1 !== $financePaymentId2 || !is_array($financePayment) || abs((float) ($financePayment['amount'] ?? 0) - 90.0) > 0.0001) {
    fwrite(STDERR, "Updated Membership payment was not propagated before allocation.\n");
    exit(1);
}

// The first allocation and every unchanged replay must resolve to the same Finance records.
$allocation1 = $bridge->syncPaidPaymentAllocation($paymentId, 'EUR', 1);
$allocation2 = $bridge->syncPaidPaymentAllocation($paymentId, 'EUR', 1);
if (!is_array($allocation1) || $allocation1 !== $allocation2 || (int) ($allocation1['obligation_id'] ?? 0) !== $obligationId2 || (int) ($allocation1['payment_id'] ?? 0) !== $financePaymentId2) {
    fwrite(STDERR, "Membership allocation replay is not stable.\n");
    exit(1);
}

$queryService = $financeComponent->getFinanceQueryService();
$allocatedObligation = null;
foreach ($queryService->listObligations(500) as $row) {
    if ((int) ($row['id'] ?? 0) === $obligationId2) {
        $allocatedObligation = $row;
        break;
    }
}
if (!is_array($allocatedObligation) || abs((float) ($allocatedObligation['allocated_amount'] ?? 0) - 90.0) > 0.0001 || ($allocatedObligation['status'] ?? '') !== 'paid') {
    fwrite(STDERR, "Membership allocation replay duplicated or corrupted the Finance allocation.\n");
    exit(1);
}

// Once allocated, changed financial history must be rejected rather than rewritten.
$updateRow('#__decaromembership_dues', ['id' => $dueId, 'amount' => '95.00']);
$changedDueRejected = false;
try {
    $bridge->syncDueToFinance($dueId, 'EUR', 1);
} catch (\RuntimeException) {
    $changedDueRejected = true;
}
if (!$changedDueRejected) {
    fwrite(STDERR, "Allocated Membership due accepted a conflicting change.\n");
    exit(1);
}
$updateRow('#__decaromembership_dues', ['id' => $dueId, 'amount' => '90.00']);

$updateRow('#__decaromembership_payments', ['id' => $paymentId, 'amount' => '95.00']);
$changedPaymentRejected = false;
try {
    $bridge->syncPaymentToFinance($paymentId, 'EUR', 1);
} catch (\RuntimeException) {
    $changedPaymentRejected = true;
}
if (!$changedPaymentRejected) {
    fwrite(STDERR, "Allocated Membership payment accepted a conflicting change.\n");
    exit(1);
}
$updateRow('#__decaromembership_payments', ['id' => $paymentId, 'amount' => '90.00']);

$allocation3 = $bridge->syncPaidPaymentAllocation($paymentId, 'EUR', 1);
if ($allocation3 !== $allocation1) {
    fwrite(STDERR, "Unchanged Membership allocation replay failed after rejected changes.\n");
    exit(1);
}

echo "Membership + Finance 1.3.0 runtime contract OK\n";
