<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

final class MembershipPersonHistoryService
{
    public function __construct(
        private DatabaseInterface $db,
        private MembershipEligibilityService $eligibility
    ) {}

    public function recordTransition(int $memberId, ?object $old, object $new, int $userId, string $created): void
    {
        if ($memberId < 1) {
            return;
        }

        $tracked = ['status', 'category_id', 'location_id', 'current_period_started_on', 'ended_on', 'rights_status'];
        $changed = $old === null;
        if ($old !== null) {
            foreach ($tracked as $field) {
                if ((string) ($old->{$field} ?? '') !== (string) ($new->{$field} ?? '')) {
                    $changed = true;
                    break;
                }
            }
        }

        if (!$changed) {
            return;
        }

        $eventType = $old === null ? 'created' : 'updated';
        if ($old !== null && (string) ($old->status ?? '') !== (string) ($new->status ?? '')) {
            $eventType = 'status';
        } elseif ($old !== null && (int) ($old->location_id ?? 0) !== (int) ($new->location_id ?? 0)) {
            $eventType = 'location';
        } elseif ($old !== null && (int) ($old->category_id ?? 0) !== (int) ($new->category_id ?? 0)) {
            $eventType = 'category';
        }

        $effectiveOn = trim((string) ($new->current_period_started_on ?? ''));
        if ($effectiveOn === '') {
            $effectiveOn = Factory::getDate()->format('Y-m-d');
        }

        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__decaromembership_member_history'))
            ->columns([
                $this->db->quoteName('member_id'),
                $this->db->quoteName('event_type'),
                $this->db->quoteName('old_status'),
                $this->db->quoteName('new_status'),
                $this->db->quoteName('old_category_id'),
                $this->db->quoteName('new_category_id'),
                $this->db->quoteName('old_location_id'),
                $this->db->quoteName('new_location_id'),
                $this->db->quoteName('effective_on'),
                $this->db->quoteName('reason'),
                $this->db->quoteName('created'),
                $this->db->quoteName('created_by'),
            ])
            ->values(':member_id,:event_type,:old_status,:new_status,:old_category,:new_category,:old_location,:new_location,:effective_on,:reason,:created,:created_by')
            ->bind(':member_id', $memberId, ParameterType::INTEGER)
            ->bind(':event_type', $eventType)
            ->bind(':old_status', $old?->status)
            ->bind(':new_status', $new->status)
            ->bind(':old_category', $old?->category_id, $old?->category_id === null ? ParameterType::NULL : ParameterType::INTEGER)
            ->bind(':new_category', $new->category_id, $new->category_id === null ? ParameterType::NULL : ParameterType::INTEGER)
            ->bind(':old_location', $old?->location_id, $old?->location_id === null ? ParameterType::NULL : ParameterType::INTEGER)
            ->bind(':new_location', $new->location_id, $new->location_id === null ? ParameterType::NULL : ParameterType::INTEGER)
            ->bind(':effective_on', $effectiveOn)
            ->bind(':reason', $new->status_reason)
            ->bind(':created', $created)
            ->bind(':created_by', $userId, ParameterType::INTEGER);
        $this->db->setQuery($query)->execute();
    }

    public function getHistoryByPersonUuid(string $personUuid): array
    {
        $this->authorise();
        $personUuid = strtolower(trim($personUuid));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $personUuid)) {
            return [];
        }

        $member = $this->loadMemberByPersonUuid($personUuid);
        if ($member === null) {
            return [];
        }

        $memberId = (int) $member->id;
        return [
            'current' => $this->currentSummary($member),
            'eligibility' => $this->eligibility->getSummary($memberId),
            'history' => $this->loadHistory($memberId),
            'transfers' => $this->loadTransfers($memberId),
        ];
    }

    private function authorise(): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', 'com_decaromembership')
            && !$user->authorise('core.admin', 'com_decaromembership')) {
            throw new RuntimeException('Not authorised.', 403);
        }
    }

    private function loadMemberByPersonUuid(string $uuid): ?object
    {
        $query = $this->db->getQuery(true)
            ->select('m.*')
            ->select($this->db->quoteName('c.name', 'category_name'))
            ->select($this->db->quoteName('l.name', 'location_name'))
            ->from($this->db->quoteName('#__decaromembership_members', 'm'))
            ->leftJoin($this->db->quoteName('#__decaromembership_categories', 'c') . ' ON ' . $this->db->quoteName('c.id') . ' = ' . $this->db->quoteName('m.category_id'))
            ->leftJoin($this->db->quoteName('#__decaromembership_locations', 'l') . ' ON ' . $this->db->quoteName('l.id') . ' = ' . $this->db->quoteName('m.location_id'))
            ->where($this->db->quoteName('m.person_uuid') . ' = :uuid')
            ->where($this->db->quoteName('m.published') . ' >= 0')
            ->bind(':uuid', $uuid);

        $row = $this->db->setQuery($query, 0, 1)->loadObject();
        return is_object($row) ? $row : null;
    }

    private function currentSummary(object $member): array
    {
        return [
            'member_id' => (int) $member->id,
            'member_number' => (string) ($member->member_number ?? ''),
            'card_number' => (string) ($member->card_number ?? ''),
            'category_id' => $member->category_id !== null ? (int) $member->category_id : null,
            'category_name' => (string) ($member->category_name ?? ''),
            'location_id' => $member->location_id !== null ? (int) $member->location_id : null,
            'location_name' => (string) ($member->location_name ?? ''),
            'status' => (string) ($member->status ?? ''),
            'status_reason' => (string) ($member->status_reason ?? ''),
            'rights_status' => (string) ($member->rights_status ?? 'normal'),
            'first_registration_date' => $member->first_registration_date ?? null,
            'current_period_started_on' => $member->current_period_started_on ?? null,
            'ended_on' => $member->ended_on ?? null,
        ];
    }

    private function loadHistory(int $memberId): array
    {
        $query = $this->db->getQuery(true)
            ->select('h.*')
            ->select($this->db->quoteName('oc.name', 'old_category_name'))
            ->select($this->db->quoteName('nc.name', 'new_category_name'))
            ->select($this->db->quoteName('ol.name', 'old_location_name'))
            ->select($this->db->quoteName('nl.name', 'new_location_name'))
            ->from($this->db->quoteName('#__decaromembership_member_history', 'h'))
            ->leftJoin($this->db->quoteName('#__decaromembership_categories', 'oc') . ' ON ' . $this->db->quoteName('oc.id') . ' = ' . $this->db->quoteName('h.old_category_id'))
            ->leftJoin($this->db->quoteName('#__decaromembership_categories', 'nc') . ' ON ' . $this->db->quoteName('nc.id') . ' = ' . $this->db->quoteName('h.new_category_id'))
            ->leftJoin($this->db->quoteName('#__decaromembership_locations', 'ol') . ' ON ' . $this->db->quoteName('ol.id') . ' = ' . $this->db->quoteName('h.old_location_id'))
            ->leftJoin($this->db->quoteName('#__decaromembership_locations', 'nl') . ' ON ' . $this->db->quoteName('nl.id') . ' = ' . $this->db->quoteName('h.new_location_id'))
            ->where($this->db->quoteName('h.member_id') . ' = :member_id')
            ->order($this->db->quoteName('h.created') . ' DESC')
            ->bind(':member_id', $memberId, ParameterType::INTEGER);

        $rows = [];
        foreach ((array) $this->db->setQuery($query)->loadAssocList() as $row) {
            unset($row['created_by']);
            $rows[] = $row;
        }
        return $rows;
    }

    private function loadTransfers(int $memberId): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                't.id','t.reference','t.requested_at','t.status','t.completed_at',
                't.delegation_status','t.card_position','t.arrears_amount',
            ])
            ->select($this->db->quoteName('fl.name', 'from_location_name'))
            ->select($this->db->quoteName('tl.name', 'to_location_name'))
            ->from($this->db->quoteName('#__decaromembership_transfers', 't'))
            ->leftJoin($this->db->quoteName('#__decaromembership_locations', 'fl') . ' ON ' . $this->db->quoteName('fl.id') . ' = ' . $this->db->quoteName('t.from_location_id'))
            ->leftJoin($this->db->quoteName('#__decaromembership_locations', 'tl') . ' ON ' . $this->db->quoteName('tl.id') . ' = ' . $this->db->quoteName('t.to_location_id'))
            ->where($this->db->quoteName('t.member_id') . ' = :member_id')
            ->where($this->db->quoteName('t.published') . ' >= 0')
            ->order($this->db->quoteName('t.id') . ' DESC')
            ->bind(':member_id', $memberId, ParameterType::INTEGER);

        return (array) $this->db->setQuery($query)->loadAssocList();
    }
}
