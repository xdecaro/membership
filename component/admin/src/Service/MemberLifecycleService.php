<?php

namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use RuntimeException;

final class MemberLifecycleService
{
    private const AUTO_NUMBER = 'automatic';
    private const DEFAULT_NUMBER_MODE = 'manual';
    private const ALLOWED_DEFAULT_STATUSES = ['pending', 'in_review', 'active'];
    private const ACTIVE_STATUSES = ['admitted', 'active'];
    private const TERMINAL_STATUSES = ['lapsed', 'resigned', 'expelled', 'deceased', 'ceased'];

    public function prepareForSave(int $id, ?object $old, array $data, array $input, string $today): array
    {
        $params = ComponentHelper::getParams('com_decaromembership');

        if ($id < 1 && !array_key_exists('status', $input)) {
            $configured = (string) $params->get('member_default_status', 'pending');
            $data['status'] = in_array($configured, self::ALLOWED_DEFAULT_STATUSES, true) ? $configured : 'pending';
        }

        $status = trim((string) ($data['status'] ?? 'pending'));
        $oldStatus = trim((string) ($old->status ?? ''));

        if ((int) ($data['category_id'] ?? 0) < 1) {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_MEMBER_CATEGORY_REQUIRED'));
        }

        if ($this->isAutomaticNumbering()) {
            $oldNumber = trim((string) ($old->member_number ?? ''));
            $data['member_number'] = $oldNumber !== '' ? $oldNumber : null;
        }

        if ($id < 1 || $status !== $oldStatus) {
            if (empty($data['status_effective_date'])) {
                $data['status_effective_date'] = $today;
            }
        }

        $becameActive = in_array($status, self::ACTIVE_STATUSES, true)
            && !in_array($oldStatus, self::ACTIVE_STATUSES, true);

        if ($becameActive) {
            if (empty($data['admission_date'])) {
                $data['admission_date'] = $today;
            }
            if (empty($data['current_membership_start_date'])) {
                $data['current_membership_start_date'] = (string) $data['admission_date'];
            }
            if (empty($data['first_registration_date'])) {
                $data['first_registration_date'] = (string) $data['admission_date'];
            }
        }

        if ($status !== $oldStatus && in_array($status, self::TERMINAL_STATUSES, true) && empty($data['cessation_date'])) {
            $data['cessation_date'] = $today;
        }

        return $data;
    }

    public function isAutomaticNumbering(): bool
    {
        return (string) ComponentHelper::getParams('com_decaromembership')->get('member_number_mode', self::DEFAULT_NUMBER_MODE) === self::AUTO_NUMBER;
    }

    public function generateNumber(int $memberId): string
    {
        if ($memberId < 1) {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_MEMBER_NUMBER_GENERATION'));
        }

        $params = ComponentHelper::getParams('com_decaromembership');
        $prefix = trim((string) $params->get('member_number_prefix', ''));
        $padding = max(1, min(12, (int) $params->get('member_number_padding', 6)));

        return $prefix . str_pad((string) $memberId, $padding, '0', STR_PAD_LEFT);
    }
}
