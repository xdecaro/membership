<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/** Creates idempotent shared notifications/tasks for existing Membership deadlines. */
final class ReminderService
{
    private DatabaseInterface $db;
    private CrossProductIntegrationService $integrations;

    public function __construct(DatabaseInterface $db, CrossProductIntegrationService $integrations)
    {
        $this->db = $db;
        $this->integrations = $integrations;
    }

    /** @return array<string,int> */
    public function process(?int $days = null, int $limit = 200): array
    {
        $params = ComponentHelper::getParams('com_decaromembership');
        $days = max(1, min(365, $days ?? (int) $params->get('integration_reminder_days', 30)));
        $limit = max(1, min(1000, $limit));
        $managerUserId = max(0, (int) $params->get('integration_manager_user_id', 0));
        $createTasks = (bool) $params->get('integration_create_tasks', 1);
        $notifyMembers = (bool) $params->get('integration_notify_members', 1);
        [$today, $until] = $this->window($days);

        $stats = ['items' => 0, 'notifications' => 0, 'tasks' => 0, 'skipped' => 0];
        foreach ($this->deadlineRows($today, $until, $limit) as $row) {
            ++$stats['items'];
            $date = (string) $row['deadline'];
            $kind = (string) $row['kind'];
            $sourceId = (string) $row['source_id'];
            $memberId = (string) $row['member_id'];
            $name = trim((string) $row['first_name'] . ' ' . (string) $row['last_name']);
            $title = $this->title($kind, $name);
            $message = $this->message($kind, $date, $name);
            $keyBase = 'membership:' . $kind . ':' . $sourceId . ':' . $date;

            $didSomething = false;
            if ($notifyMembers && (int) $row['user_id'] > 0) {
                $id = $this->integrations->publishNotification([
                    'recipient_type' => 'user',
                    'recipient_id' => (string) (int) $row['user_id'],
                    'category' => 'membership',
                    'priority' => $date <= $today ? 'high' : 'normal',
                    'title' => $title,
                    'message' => $message,
                    'source_entity' => $kind,
                    'source_id' => $sourceId,
                    'external_key' => $keyBase . ':member',
                    'payload' => ['member_id' => $memberId, 'deadline' => $date, 'kind' => $kind],
                ]);
                if ($id !== null) { ++$stats['notifications']; $didSomething = true; }
            }

            if ($createTasks && $managerUserId > 0) {
                $id = $this->integrations->createTask([
                    'title' => $title,
                    'description' => $message,
                    'priority' => $date <= $today ? 'high' : 'normal',
                    'due_at' => $date . ' 09:00:00',
                    'source_entity' => $kind,
                    'source_id' => $sourceId,
                    'external_key' => $keyBase . ':manager-task',
                ], ['type' => 'user', 'id' => (string) $managerUserId]);
                if ($id !== null) { ++$stats['tasks']; $didSomething = true; }
            }

            if (!$didSomething) { ++$stats['skipped']; }
        }
        return $stats;
    }

    /** @return array<int,array<string,mixed>> */
    private function deadlineRows(string $today, string $until, int $limit): array
    {
        $parts = [
            "SELECT 'renewal' AS kind, r.id AS source_id, r.member_id, r.expiry_date AS deadline, m.user_id, m.first_name, m.last_name FROM #__decaromembership_renewals r INNER JOIN #__decaromembership_members m ON m.id=r.member_id WHERE r.published=1 AND r.expiry_date BETWEEN " . $this->db->quote($today) . ' AND ' . $this->db->quote($until),
            "SELECT 'card' AS kind, c.id AS source_id, c.member_id, c.expires_at AS deadline, m.user_id, m.first_name, m.last_name FROM #__decaromembership_cards c INNER JOIN #__decaromembership_members m ON m.id=c.member_id WHERE c.published=1 AND c.expires_at BETWEEN " . $this->db->quote($today) . ' AND ' . $this->db->quote($until),
            "SELECT 'document' AS kind, d.id AS source_id, d.member_id, d.expires_at AS deadline, m.user_id, m.first_name, m.last_name FROM #__decaromembership_documents d INNER JOIN #__decaromembership_members m ON m.id=d.member_id WHERE d.published=1 AND d.member_id IS NOT NULL AND d.expires_at BETWEEN " . $this->db->quote($today) . ' AND ' . $this->db->quote($until),
            "SELECT 'due' AS kind, d.id AS source_id, d.member_id, d.due_date AS deadline, m.user_id, m.first_name, m.last_name FROM #__decaromembership_dues d INNER JOIN #__decaromembership_members m ON m.id=d.member_id WHERE d.published=1 AND d.status <> 'paid' AND d.due_date BETWEEN " . $this->db->quote($today) . ' AND ' . $this->db->quote($until),
        ];
        $sql = implode(' UNION ALL ', $parts) . ' ORDER BY deadline ASC LIMIT ' . (int) $limit;
        return array_values((array) $this->db->setQuery($sql)->loadAssocList());
    }

    private function title(string $kind, string $name): string
    {
        $labels = ['renewal' => 'Membership renewal due', 'card' => 'Membership card expiring', 'document' => 'Membership document expiring', 'due' => 'Membership fee due'];
        return ($labels[$kind] ?? 'Membership deadline') . ($name !== '' ? ': ' . $name : '');
    }

    private function message(string $kind, string $date, string $name): string
    {
        return $this->title($kind, $name) . ' (' . $date . ').';
    }

    private function window(int $days): array
    {
        return [Factory::getDate('now', 'UTC')->format('Y-m-d'), Factory::getDate('+' . $days . ' days', 'UTC')->format('Y-m-d')];
    }
}
