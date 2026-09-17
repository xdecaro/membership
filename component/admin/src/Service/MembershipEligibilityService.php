<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class MembershipEligibilityService
{
    public function __construct(private DatabaseInterface $db) {}

    public function isActiveMember(int $memberId): bool
    {
        $member = $this->loadMember($memberId);
        if ($member === null || (int) ($member->published ?? 0) !== 1) {
            return false;
        }

        if (strtolower(trim((string) ($member->status ?? ''))) !== 'active') {
            return false;
        }

        $endedOn = trim((string) ($member->ended_on ?? ''));
        return $endedOn === '' || $endedOn >= Factory::getDate()->format('Y-m-d');
    }

    public function isFeeCurrent(int $memberId, ?string $associationYear = null): bool
    {
        if ($memberId < 1) {
            return false;
        }

        $associationYear = trim((string) ($associationYear ?? Factory::getDate()->format('Y')));
        if ($associationYear === '') {
            return false;
        }

        $query = $this->db->getQuery(true)
            ->select([$this->db->quoteName('status'), $this->db->quoteName('payment_status')])
            ->from($this->db->quoteName('#__decaromembership_renewals'))
            ->where($this->db->quoteName('member_id') . ' = :member_id')
            ->where($this->db->quoteName('association_year') . ' = :association_year')
            ->where($this->db->quoteName('published') . ' >= 0')
            ->order($this->db->quoteName('id') . ' DESC')
            ->bind(':member_id', $memberId, ParameterType::INTEGER)
            ->bind(':association_year', $associationYear);
        $row = $this->db->setQuery($query, 0, 1)->loadObject();
        if (!is_object($row)) {
            return false;
        }

        return strtolower((string) ($row->payment_status ?? '')) === 'paid'
            || strtolower((string) ($row->status ?? '')) === 'completed';
    }

    public function canVote(int $memberId): bool
    {
        return $this->evaluateRight($memberId, 'vote');
    }

    public function canBeCandidate(int $memberId): bool
    {
        return $this->evaluateRight($memberId, 'candidate');
    }

    public function getMembershipSeniorityDays(int $memberId): int
    {
        $member = $this->loadMember($memberId);
        if ($member === null) {
            return 0;
        }

        $start = trim((string) ($member->first_registration_date ?? ''));
        $credit = max(0, (int) ($member->seniority_credit_days ?? 0));
        if ($start === '') {
            return $credit;
        }

        try {
            $startDate = new \DateTimeImmutable($start);
            $today = new \DateTimeImmutable(Factory::getDate()->format('Y-m-d'));
        } catch (\Throwable) {
            return $credit;
        }

        $elapsed = $startDate <= $today ? (int) $startDate->diff($today)->days : 0;
        return $elapsed + $credit;
    }

    public function getSummary(int $memberId): array
    {
        return [
            'active' => $this->isActiveMember($memberId),
            'fee_current' => $this->isFeeCurrent($memberId),
            'can_vote' => $this->canVote($memberId),
            'can_be_candidate' => $this->canBeCandidate($memberId),
            'seniority_days' => $this->getMembershipSeniorityDays($memberId),
        ];
    }

    private function evaluateRight(int $memberId, string $right): bool
    {
        $member = $this->loadMember($memberId);
        if ($member === null || !$this->isActiveMember($memberId)) {
            return false;
        }

        $overrideField = $right === 'vote' ? 'can_vote_override' : 'can_candidate_override';
        if (property_exists($member, $overrideField) && $member->{$overrideField} !== null) {
            return (int) $member->{$overrideField} === 1;
        }

        if (strtolower(trim((string) ($member->rights_status ?? 'normal'))) !== 'normal') {
            return false;
        }

        $params = ComponentHelper::getParams('com_decaromembership');
        $minimumDays = max(0, (int) $params->get(
            $right === 'vote' ? 'minimum_seniority_days_vote' : 'minimum_seniority_days_candidate',
            0
        ));
        if ($this->getMembershipSeniorityDays($memberId) < $minimumDays) {
            return false;
        }

        $requireFee = (int) $params->get(
            $right === 'vote' ? 'voting_require_current_fee' : 'candidacy_require_current_fee',
            0
        ) === 1;

        return !$requireFee || $this->isFeeCurrent($memberId);
    }

    private function loadMember(int $memberId): ?object
    {
        if ($memberId < 1) {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__decaromembership_members'))
            ->where($this->db->quoteName('id') . ' = :member_id')
            ->bind(':member_id', $memberId, ParameterType::INTEGER);

        $row = $this->db->setQuery($query, 0, 1)->loadObject();
        return is_object($row) ? $row : null;
    }
}
