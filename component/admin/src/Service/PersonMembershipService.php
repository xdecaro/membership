<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use RuntimeException;

final class PersonMembershipService
{
    public function __construct(private DatabaseInterface $db) {}

    public function getMembershipsByPersonUuid(string $personUuid): array
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', 'com_decaromembership') && !$user->authorise('core.admin', 'com_decaromembership')) {
            throw new RuntimeException('Not authorised.', 403);
        }

        $personUuid = strtolower(trim($personUuid));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $personUuid)) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('m.id', 'member_id'),
                $this->db->quoteName('m.member_number'),
                $this->db->quoteName('m.status'),
                $this->db->quoteName('m.first_registration_date'),
                $this->db->quoteName('m.application_date'),
                $this->db->quoteName('m.admission_date'),
                $this->db->quoteName('m.status_effective_date'),
                $this->db->quoteName('m.cessation_date'),
                $this->db->quoteName('m.voting_active'),
                $this->db->quoteName('m.voting_passive'),
                $this->db->quoteName('m.category_id'),
                $this->db->quoteName('m.location_id'),
                $this->db->quoteName('c.name', 'category_name'),
                $this->db->quoteName('l.name', 'location_name'),
            ])
            ->from($this->db->quoteName('#__decaromembership_members', 'm'))
            ->leftJoin($this->db->quoteName('#__decaromembership_categories', 'c') . ' ON ' . $this->db->quoteName('c.id') . ' = ' . $this->db->quoteName('m.category_id'))
            ->leftJoin($this->db->quoteName('#__decaromembership_locations', 'l') . ' ON ' . $this->db->quoteName('l.id') . ' = ' . $this->db->quoteName('m.location_id'))
            ->where($this->db->quoteName('m.person_uuid') . ' = :uuid')
            ->where($this->db->quoteName('m.published') . ' >= 0')
            ->bind(':uuid', $personUuid);

        return array_values((array) $this->db->setQuery($query)->loadAssocList());
    }

    public function getHistoryByPersonUuid(string $personUuid, int $limit = 100): array
    {
        $memberships = $this->getMembershipsByPersonUuid($personUuid);
        if ($memberships === []) {
            return [];
        }

        $memberId = (int) ($memberships[0]['member_id'] ?? 0);
        if ($memberId < 1) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__decaromembership_member_history'))
            ->where($this->db->quoteName('member_id') . ' = :member_id')
            ->order($this->db->quoteName('created') . ' DESC')
            ->bind(':member_id', $memberId, \Joomla\Database\ParameterType::INTEGER);

        return array_values((array) $this->db->setQuery($query, 0, $limit)->loadAssocList());
    }
}
