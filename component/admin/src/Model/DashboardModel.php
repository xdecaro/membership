<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;
use Xdecaro\Component\Decaromembership\Administrator\Helper\EntityRegistry;
use Xdecaro\Component\Decaromembership\Administrator\Helper\MembershipHelper;

final class DashboardModel extends BaseDatabaseModel
{
    public function getDashboardData(): array
    {
        /** @var DatabaseInterface $db */
        $db = $this->getDatabase();
        $counts = [];

        foreach (EntityRegistry::all() as $key => $config) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName($config['table']));

            // Joomla trash is a management state, not an active business record.
            // Keep trashed rows available for recovery, but exclude them from KPIs.
            if (isset($config['fields']['published'])) {
                $query->where($db->quoteName('published') . ' >= 0');
            }

            $counts[$key] = (int) $db->setQuery($query)->loadResult();
        }

        return ['counts' => $counts, 'integrations' => MembershipHelper::integrations()];
    }
}
