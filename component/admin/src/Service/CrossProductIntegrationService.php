<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use InvalidArgumentException;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;
use Throwable;

final class CrossProductIntegrationService
{
    public const COMPONENT = 'com_decaromembership';

    public function __construct(private DatabaseInterface $db) {}

    public function financeAvailable(): bool
    {
        return ComponentHelper::isEnabled('com_decarofinance');
    }

    public function publishNotification(array $data): ?int
    {
        if (!ComponentHelper::isEnabled('com_xdecaronotifications')) { return null; }
        $data['source_component'] = self::COMPONENT;
        try {
            $component = Factory::getApplication()->bootComponent('com_xdecaronotifications');
            if (!is_object($component) || !method_exists($component, 'getNotificationService')) { return null; }
            $service = $component->getNotificationService();
            return is_object($service) && method_exists($service, 'create') ? (int) $service->create($data) : null;
        } catch (Throwable $e) {
            Log::add('Membership notification bridge: ' . $e->getMessage(), Log::WARNING, 'com_decaromembership.integration');
            return null;
        }
    }

    public function createTask(array $data, ?array $assignee = null, int $actorUserId = 0): ?int
    {
        if (!ComponentHelper::isEnabled('com_xdecarotasks')) { return null; }
        $data['source_component'] = self::COMPONENT;
        try {
            $component = Factory::getApplication()->bootComponent('com_xdecarotasks');
            if (!is_object($component) || !method_exists($component, 'getTaskService')) { return null; }
            $service = $component->getTaskService();
            if (!is_object($service) || !method_exists($service, 'create')) { return null; }
            $id = (int) $service->create($data, max(0, $actorUserId));
            if ($id > 0 && is_array($assignee) && method_exists($service, 'assign')) {
                $type = trim((string) ($assignee['type'] ?? ''));
                $recipient = trim((string) ($assignee['id'] ?? ''));
                if ($type !== '' && $recipient !== '') {
                    $service->assign($id, $type, $recipient, max(0, $actorUserId), true);
                }
            }
            return $id > 0 ? $id : null;
        } catch (Throwable $e) {
            Log::add('Membership task bridge: ' . $e->getMessage(), Log::WARNING, 'com_decaromembership.integration');
            return null;
        }
    }

    /** Synchronize one Membership due to a Finance obligation. */
    public function syncDueToFinance(int $dueId, string $currency = 'EUR', int $actorUserId = 0): ?int
    {
        $dueId = $this->positiveId($dueId, 'due');

        return $this->withFinanceService(function (object $finance) use ($dueId, $currency, $actorUserId): int {
            $due = $this->loadLocalRow('#__decaromembership_dues', $dueId, 'due');
            return $this->upsertDue($finance, $due, $currency, $actorUserId);
        });
    }

    /** Synchronize one paid Membership payment to Finance. */
    public function syncPaymentToFinance(int $paymentId, string $currency = 'EUR', int $actorUserId = 0): ?int
    {
        $paymentId = $this->positiveId($paymentId, 'payment');

        return $this->withFinanceService(function (object $finance) use ($paymentId, $currency, $actorUserId): int {
            $payment = $this->loadLocalRow('#__decaromembership_payments', $paymentId, 'payment');
            return $this->upsertPayment($finance, $payment, $currency, $actorUserId);
        });
    }

    /**
     * Synchronize a paid Membership payment and its linked due, then allocate it
     * exactly once. Repeating the operation with unchanged local records is safe.
     */
    public function syncPaidPaymentAllocation(int $paymentId, string $currency = 'EUR', int $actorUserId = 0): ?array
    {
        $paymentId = $this->positiveId($paymentId, 'payment');

        return $this->withFinanceService(function (object $finance) use ($paymentId, $currency, $actorUserId): array {
            $payment = $this->loadLocalRow('#__decaromembership_payments', $paymentId, 'payment');
            $dueId = (int) ($payment->due_id ?? 0);
            if ($dueId < 1) { throw new RuntimeException('Membership payment is not linked to a due.'); }

            $due = $this->loadLocalRow('#__decaromembership_dues', $dueId, 'due');
            if ((int) ($payment->member_id ?? 0) !== (int) ($due->member_id ?? 0)) {
                throw new RuntimeException('Membership payment and due belong to different members.');
            }

            $obligationId = $this->upsertDue($finance, $due, $currency, $actorUserId);
            $financePaymentId = $this->upsertPayment($finance, $payment, $currency, $actorUserId);
            if (!method_exists($finance, 'allocatePaymentIdempotent')) {
                throw new RuntimeException('Finance replay-safe allocation API is unavailable.');
            }

            $finance->allocatePaymentIdempotent($financePaymentId, $obligationId, $this->positiveAmount($payment->amount ?? 0));

            return ['obligation_id' => $obligationId, 'payment_id' => $financePaymentId];
        });
    }

    private function upsertDue(object $finance, object $due, string $currency, int $actorUserId): int
    {
        if (!method_exists($finance, 'upsertObligation')) {
            throw new RuntimeException('Finance obligation upsert API is unavailable.');
        }

        $dueId = $this->positiveId((int) ($due->id ?? 0), 'due');
        $memberId = $this->positiveId((int) ($due->member_id ?? 0), 'member');
        $year = trim((string) ($due->association_year ?? ''));
        if ($year === '' || mb_strlen($year) > 20) { throw new RuntimeException('Membership due association year is invalid.'); }

        return (int) $finance->upsertObligation([
            'external_key' => 'membership:due:' . $dueId,
            'source_component' => self::COMPONENT,
            'source_entity' => 'due',
            'source_id' => $dueId,
            'debtor_component' => self::COMPONENT,
            'debtor_entity' => 'member',
            'debtor_id' => $memberId,
            'kind' => 'membership_due',
            'description' => 'Membership due ' . $year,
            'amount' => $this->positiveAmount($due->amount ?? 0),
            'currency' => $currency,
            'due_date' => $this->nullableDate($due->due_date ?? null),
        ], max(0, $actorUserId));
    }

    private function upsertPayment(object $finance, object $payment, string $currency, int $actorUserId): int
    {
        if (!method_exists($finance, 'upsertPayment')) {
            throw new RuntimeException('Finance payment upsert API is unavailable.');
        }

        $paymentId = $this->positiveId((int) ($payment->id ?? 0), 'payment');
        $memberId = $this->positiveId((int) ($payment->member_id ?? 0), 'member');
        if (strtolower(trim((string) ($payment->status ?? ''))) !== 'paid') {
            throw new RuntimeException('Only Membership payments in paid state can be synchronized to Finance.');
        }

        return (int) $finance->upsertPayment([
            'external_key' => 'membership:payment:' . $paymentId,
            'payer_component' => self::COMPONENT,
            'payer_entity' => 'member',
            'payer_id' => $memberId,
            'amount' => $this->positiveAmount($payment->amount ?? 0),
            'currency' => $currency,
            'paid_at' => $this->nullableDate($payment->paid_at ?? null),
            'method' => $this->nullableToken($payment->method ?? null),
            'reference' => $this->nullableText($payment->reference ?? null, 191),
        ], max(0, $actorUserId));
    }

    /** Finance absence is optional; installed-provider failures are never hidden. */
    private function withFinanceService(callable $operation): mixed
    {
        if (!$this->financeAvailable()) { return null; }

        try {
            $component = Factory::getApplication()->bootComponent('com_decarofinance');
            if (!is_object($component) || !method_exists($component, 'getFinanceService')) {
                throw new RuntimeException('Finance public component service is unavailable.');
            }
            $finance = $component->getFinanceService();
            if (!is_object($finance)) { throw new RuntimeException('Finance service is unavailable.'); }
            return $operation($finance);
        } catch (Throwable $e) {
            Log::add('Membership Finance bridge: ' . $e->getMessage(), Log::ERROR, 'com_decaromembership.integration');
            throw $e;
        }
    }

    private function loadLocalRow(string $table, int $id, string $label): object
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName($table))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);
        $row = $this->db->setQuery($query, 0, 1)->loadObject();
        if (!is_object($row)) { throw new RuntimeException('Membership ' . $label . ' not found.'); }
        return $row;
    }

    private function positiveId(int $value, string $label): int
    {
        if ($value < 1) { throw new InvalidArgumentException('Invalid Membership ' . $label . ' identifier.'); }
        return $value;
    }

    private function positiveAmount(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || !is_numeric($value) || (float) $value <= 0) {
            throw new InvalidArgumentException('Membership amount must be greater than zero.');
        }
        return $value;
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullableToken(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullableText(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') { return null; }
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }
}
