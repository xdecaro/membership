<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class MembershipHistoryService
{
    public function __construct(private DatabaseInterface $db) {}

    public function recordMemberChange(
        int $memberId,
        ?object $old,
        object $new,
        int $userId,
        string $created,
        ?string $sourceEntityType = null,
        ?int $sourceEntityId = null
    ): void {
        if ($memberId < 1) {
            return;
        }

        $changes = [];
        foreach (['status', 'category_id', 'location_id', 'voting_active', 'voting_passive'] as $field) {
            $before = $old?->{$field} ?? null;
            $after = $new->{$field} ?? null;
            if ((string) $before !== (string) $after) {
                $changes[] = $field;
            }
        }

        if ($old !== null && $changes === []) {
            return;
        }

        $eventType = $old === null ? 'created' : 'lifecycle_change';
        if ($old !== null && count($changes) === 1) {
            $eventType = match ($changes[0]) {
                'status' => 'status_change',
                'category_id' => 'category_change',
                'location_id' => 'location_change',
                'voting_active', 'voting_passive' => 'rights_change',
                default => 'lifecycle_change',
            };
        }

        $effectiveDate = trim((string) ($new->status_effective_date ?? ''));
        if ($effectiveDate === '') {
            $effectiveDate = substr($created, 0, 10);
        }

        $metadata = json_encode([
            'changed_fields' => $changes,
            'old_voting_active' => $old?->voting_active ?? null,
            'new_voting_active' => $new->voting_active ?? null,
            'old_voting_passive' => $old?->voting_passive ?? null,
            'new_voting_passive' => $new->voting_passive ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

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
                $this->db->quoteName('effective_date'),
                $this->db->quoteName('source_entity_type'),
                $this->db->quoteName('source_entity_id'),
                $this->db->quoteName('metadata_json'),
                $this->db->quoteName('user_id'),
                $this->db->quoteName('created'),
            ])
            ->values(':member_id,:event_type,:old_status,:new_status,:old_category,:new_category,:old_location,:new_location,:effective_date,:source_type,:source_id,:metadata,:user_id,:created')
            ->bind(':member_id', $memberId, ParameterType::INTEGER)
            ->bind(':event_type', $eventType)
            ->bind(':old_status', $old?->status)
            ->bind(':new_status', $new->status)
            ->bind(':old_category', $old?->category_id, $old?->category_id === null ? ParameterType::NULL : ParameterType::INTEGER)
            ->bind(':new_category', $new->category_id, $new->category_id === null ? ParameterType::NULL : ParameterType::INTEGER)
            ->bind(':old_location', $old?->location_id, $old?->location_id === null ? ParameterType::NULL : ParameterType::INTEGER)
            ->bind(':new_location', $new->location_id, $new->location_id === null ? ParameterType::NULL : ParameterType::INTEGER)
            ->bind(':effective_date', $effectiveDate)
            ->bind(':source_type', $sourceEntityType)
            ->bind(':source_id', $sourceEntityId, $sourceEntityId === null ? ParameterType::NULL : ParameterType::INTEGER)
            ->bind(':metadata', $metadata)
            ->bind(':user_id', $userId, ParameterType::INTEGER)
            ->bind(':created', $created);

        $this->db->setQuery($query)->execute();
    }
}
