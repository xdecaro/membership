<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use RuntimeException;

final class AnalyticsSourceService
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db) { $this->db = $db; }

    public function getMetrics(): array
    {
        $this->assertAuthorised();
        return [
            ['key' => 'membership.members.total', 'label' => 'Members'],
            ['key' => 'membership.members.active', 'label' => 'Active members'],
            ['key' => 'membership.renewals.due', 'label' => 'Renewals due'],
            ['key' => 'membership.dues.unpaid_amount', 'label' => 'Unpaid dues', 'unit' => 'currency'],
            ['key' => 'membership.cards.expiring_30d', 'label' => 'Cards expiring in 30 days'],
        ];
    }

    public function getDatasets(): array
    {
        $this->assertAuthorised();
        return [
            ['key' => 'membership.renewals.by_status', 'label' => 'Renewals by status'],
            ['key' => 'membership.dues.by_status', 'label' => 'Dues by status'],
            ['key' => 'membership.expiring', 'label' => 'Upcoming membership expiries'],
        ];
    }

    public function getMetric(string $key, array $context = []): array
    {
        $this->assertAuthorised();
        if ($key === 'membership.members.total') {
            return ['value' => $this->count('#__decaromembership_members'), 'label' => 'Members'];
        }
        if ($key === 'membership.members.active') {
            $status = 'active';
            $query = $this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__decaromembership_members'))->where($this->db->quoteName('status') . ' = :status')->where($this->db->quoteName('published') . ' = 1')->bind(':status', $status);
            return ['value' => (int) $this->db->setQuery($query)->loadResult(), 'label' => 'Active members'];
        }
        if ($key === 'membership.renewals.due') {
            $status = 'due';
            $query = $this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__decaromembership_renewals'))->where($this->db->quoteName('status') . ' = :status')->where($this->db->quoteName('published') . ' = 1')->bind(':status', $status);
            return ['value' => (int) $this->db->setQuery($query)->loadResult(), 'label' => 'Renewals due'];
        }
        if ($key === 'membership.dues.unpaid_amount') {
            $paid = 'paid';
            $query = $this->db->getQuery(true)
                ->select('COALESCE(SUM(GREATEST(' . $this->db->quoteName('amount') . ' - ' . $this->db->quoteName('paid_amount') . ', 0)), 0)')
                ->from($this->db->quoteName('#__decaromembership_dues'))
                ->where($this->db->quoteName('status') . ' <> :paid')->where($this->db->quoteName('published') . ' = 1')->bind(':paid', $paid);
            return ['value' => (float) $this->db->setQuery($query)->loadResult(), 'label' => 'Unpaid dues', 'unit' => 'currency'];
        }
        if ($key === 'membership.cards.expiring_30d') {
            [$today, $until] = $this->window(30);
            $query = $this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__decaromembership_cards'))->where($this->db->quoteName('expires_at') . ' BETWEEN :today AND :until')->where($this->db->quoteName('published') . ' = 1')->bind(':today', $today)->bind(':until', $until);
            return ['value' => (int) $this->db->setQuery($query)->loadResult(), 'label' => 'Cards expiring in 30 days'];
        }
        throw new \InvalidArgumentException('Unknown Membership analytics metric: ' . $key);
    }

    public function getDataset(string $key, array $context = []): array
    {
        $this->assertAuthorised();
        $limit = max(1, min(500, (int) ($context['limit'] ?? 100)));
        if ($key === 'membership.renewals.by_status') {
            return $this->groupStatus('#__decaromembership_renewals', $limit);
        }
        if ($key === 'membership.dues.by_status') {
            return $this->groupStatus('#__decaromembership_dues', $limit);
        }
        if ($key === 'membership.expiring') {
            $days = max(1, min(365, (int) ($context['days'] ?? 30)));
            [$today, $until] = $this->window($days);
            $query = $this->db->getQuery(true)
                ->select(['r.id','r.member_id','r.association_year','r.expiry_date','r.status','m.member_number','m.first_name','m.last_name'])
                ->from($this->db->quoteName('#__decaromembership_renewals', 'r'))
                ->join('INNER', $this->db->quoteName('#__decaromembership_members', 'm') . ' ON m.id = r.member_id')
                ->where('r.expiry_date BETWEEN :today AND :until')->where('r.published = 1')->order('r.expiry_date ASC')->bind(':today', $today)->bind(':until', $until);
            return array_values((array) $this->db->setQuery($query, 0, $limit)->loadAssocList());
        }
        throw new \InvalidArgumentException('Unknown Membership analytics dataset: ' . $key);
    }

    private function groupStatus(string $table, int $limit): array
    {
        $query = $this->db->getQuery(true)->select([$this->db->quoteName('status'), 'COUNT(*) AS total'])->from($this->db->quoteName($table))->where($this->db->quoteName('published') . ' = 1')->group($this->db->quoteName('status'))->order('total DESC');
        return array_values((array) $this->db->setQuery($query, 0, $limit)->loadAssocList());
    }

    private function count(string $table): int
    {
        $query = $this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName($table))->where($this->db->quoteName('published') . ' = 1');
        return (int) $this->db->setQuery($query)->loadResult();
    }

    private function window(int $days): array
    {
        $today = Factory::getDate('now', 'UTC')->format('Y-m-d');
        $until = Factory::getDate('+' . $days . ' days', 'UTC')->format('Y-m-d');
        return [$today, $until];
    }

    private function assertAuthorised(): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', 'com_decaromembership') && !$user->authorise('core.admin', 'com_decaromembership')) {
            throw new RuntimeException('Not authorised to read Membership analytics.', 403);
        }
    }
}
