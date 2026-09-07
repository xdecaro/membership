<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;
defined('_JEXEC') or die;
use Joomla\Database\DatabaseInterface;

final class AuditService
{
    public function __construct(private DatabaseInterface $db) {}

    public function record(string $entity, int $entityId, string $action, int $userId, ?object $old, ?object $new, string $created): void
    {
        $q = $this->db->getQuery(true)->insert($this->db->quoteName('#__decaromembership_audit_log'))
            ->columns([$this->db->quoteName('entity_type'), $this->db->quoteName('entity_id'), $this->db->quoteName('action'), $this->db->quoteName('user_id'), $this->db->quoteName('old_values'), $this->db->quoteName('new_values'), $this->db->quoteName('created')])
            ->values(':entity,:entity_id,:action,:user_id,:old_values,:new_values,:created')
            ->bind(':entity', $entity)->bind(':entity_id', $entityId)->bind(':action', $action)->bind(':user_id', $userId)
            ->bind(':old_values', $old ? json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null)
            ->bind(':new_values', $new ? json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null)->bind(':created', $created);
        $this->db->setQuery($q)->execute();
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
