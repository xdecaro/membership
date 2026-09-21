<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

final class PaymentAllocationService
{
    public function __construct(private DatabaseInterface $db)
    {
    }

    public function validateDueMember(int $dueId, int $memberId): void
    {
        if ($dueId < 1) {
            return;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('member_id'))
            ->from($this->db->quoteName('#__decaromembership_dues'))
            ->where($this->db->quoteName('id') . ' = :due_id')
            ->bind(':due_id', $dueId, ParameterType::INTEGER);

        $dueMemberId = (int) $this->db->setQuery($query)->loadResult();

        if ($dueMemberId < 1 || $dueMemberId !== $memberId) {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_PAYMENT_DUE_MEMBER_MISMATCH'));
        }
    }

    public function recalculateDue(int $dueId, int $userId, string $now): void
    {
        if ($dueId < 1) {
            return;
        }

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('amount'),
                $this->db->quoteName('status'),
            ])
            ->from($this->db->quoteName('#__decaromembership_dues'))
            ->where($this->db->quoteName('id') . ' = :due_id')
            ->bind(':due_id', $dueId, ParameterType::INTEGER);

        $due = $this->db->setQuery($query)->loadObject();

        if (!$due) {
            return;
        }

        $paidQuery = $this->db->getQuery(true)
            ->select('COALESCE(SUM(' . $this->db->quoteName('amount') . '), 0)')
            ->from($this->db->quoteName('#__decaromembership_payments'))
            ->where($this->db->quoteName('due_id') . ' = :due_id')
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('paid'))
            ->where($this->db->quoteName('published') . ' = 1')
            ->bind(':due_id', $dueId, ParameterType::INTEGER);

        $paidAmount = round((float) $this->db->setQuery($paidQuery)->loadResult(), 2);
        $dueAmount = round((float) ($due->amount ?? 0), 2);
        $currentStatus = (string) ($due->status ?? 'unpaid');

        if ($currentStatus === 'waived') {
            $status = 'waived';
        } elseif ($paidAmount <= 0) {
            $status = 'unpaid';
        } elseif ($paidAmount + 0.00001 < $dueAmount) {
            $status = 'partial';
        } else {
            $status = 'paid';
        }

        $update = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__decaromembership_dues'))
            ->set($this->db->quoteName('paid_amount') . ' = :paid_amount')
            ->set($this->db->quoteName('status') . ' = :status')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->set($this->db->quoteName('modified_by') . ' = :modified_by')
            ->where($this->db->quoteName('id') . ' = :due_id')
            ->bind(':paid_amount', $paidAmount)
            ->bind(':status', $status)
            ->bind(':modified', $now)
            ->bind(':modified_by', $userId, ParameterType::INTEGER)
            ->bind(':due_id', $dueId, ParameterType::INTEGER);

        $this->db->setQuery($update)->execute();
    }
}
