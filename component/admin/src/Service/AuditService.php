<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;
defined('_JEXEC') or die;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class AuditService
{
    public function __construct(private DatabaseInterface $db) {}

    public function record(string $entity, int $entityId, string $action, int $userId, ?object $old, ?object $new, string $created): void
    {
        $oldValues = $old ? json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $newValues = $new ? json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        $q = $this->db->getQuery(true)->insert($this->db->quoteName('#__decaromembership_audit_log'))
            ->columns([$this->db->quoteName('entity_type'), $this->db->quoteName('entity_id'), $this->db->quoteName('action'), $this->db->quoteName('user_id'), $this->db->quoteName('old_values'), $this->db->quoteName('new_values'), $this->db->quoteName('created')])
            ->values(':entity,:entity_id,:action,:user_id,:old_values,:new_values,:created')
            ->bind(':entity', $entity)
            ->bind(':entity_id', $entityId, ParameterType::INTEGER)
            ->bind(':action', $action)
            ->bind(':user_id', $userId, ParameterType::INTEGER)
            ->bind(':old_values', $oldValues)
            ->bind(':new_values', $newValues)
            ->bind(':created', $created);
        $this->db->setQuery($q)->execute();
    }

    public function personLink(int $memberId, string $action, ?string $oldUuid, ?string $newUuid, int $userId, string $created): void
    {
        $allowed = ['people_link', 'people_relink', 'people_backfill'];
        if (!in_array($action, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported People link audit action.');
        }

        $old = $oldUuid !== null ? (object) ['person_uuid' => $oldUuid] : null;
        $new = $newUuid !== null ? (object) ['person_uuid' => $newUuid] : null;
        $this->record('members', $memberId, $action, $userId, $old, $new, $created);
    }

    public function caseStatus(int $caseId, ?int $oldStatusId, ?int $newStatusId, int $userId, string $created): void
    {
        $q = $this->db->getQuery(true)->insert($this->db->quoteName('#__decaromembership_case_status_history'))
            ->columns([$this->db->quoteName('case_id'), $this->db->quoteName('old_status_id'), $this->db->quoteName('new_status_id'), $this->db->quoteName('user_id'), $this->db->quoteName('created')])
            ->values(':case_id,:old_status,:new_status,:user_id,:created')
            ->bind(':case_id', $caseId)->bind(':old_status', $oldStatusId)->bind(':new_status', $newStatusId)->bind(':user_id', $userId)->bind(':created', $created);
        $this->db->setQuery($q)->execute();
    }
}
