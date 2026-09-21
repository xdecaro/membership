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
$expect(version_compare($version, '1.9.14', '>='), 'VERSION must be 1.9.14 or newer.');

$finance = (string) file_get_contents($root . '/component/admin/src/Config/FinanceEntities.php');
$cases = (string) file_get_contents($root . '/component/admin/src/Config/CaseEntities.php');
$model = (string) file_get_contents($root . '/component/admin/src/Model/RecordModel.php');
$service = (string) file_get_contents($root . '/component/admin/src/Service/PaymentAllocationService.php');
$runtime = (string) file_get_contents($root . '/tests/member-lifecycle-runtime.php');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.9.14.sql');

foreach ([
    "'status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PAYMENT_STATUS','type'=>'select','required'=>true,'default'=>'pending'",
    "'published'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PUBLISHED','type'=>'published','default'=>1]",
] as $marker) {
    $expect(str_contains($finance, $marker), "Payment form marker missing {$marker}.");
}

$renewalStart = strpos($cases, "'renewals'=>");
$cardStart = strpos($cases, "'cards'=>", $renewalStart === false ? 0 : $renewalStart);
$renewalBlock = ($renewalStart !== false && $cardStart !== false)
    ? substr($cases, $renewalStart, $cardStart - $renewalStart)
    : '';
$expect($renewalBlock !== '', 'Renewal config block could not be isolated.');
$expect(!str_contains($renewalBlock, "'amount'=>"), 'Renewals must not own payment amount.');
$expect(!str_contains($renewalBlock, "'payment_status'=>"), 'Renewals must not own payment status.');

foreach ([
    'final class PaymentAllocationService',
    'validateDueMember',
    'recalculateDue',
    "#__decaromembership_payments",
    "'paid'",
] as $marker) {
    $expect(str_contains($service, $marker), "Payment allocation service marker missing {$marker}.");
}

foreach ([
    'new PaymentAllocationService',
    'recalculatePaymentDues',
    'COM_DECAROMEMBERSHIP_ERROR_PAYMENT_DUE_MEMBER_MISMATCH',
] as $marker) {
    $expect(str_contains($model . $service, $marker), "Payment synchronization marker missing {$marker}.");
}

foreach ([
    'Paid payment did not update due paid_amount to 40.00.',
    'Updated paid payment did not update due status to paid.',
    'Trashed payment did not restore due status to unpaid.',
    'Payment linked to a due belonging to another member must be rejected.',
] as $marker) {
    $expect(str_contains($runtime, $marker), "Runtime payment regression missing {$marker}.");
}

$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b/i', $update), '1.9.14 migration marker must be non-destructive.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Membership 1.9.14 payment sync contract OK\n";
