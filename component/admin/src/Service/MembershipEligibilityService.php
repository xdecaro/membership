<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

final class MembershipEligibilityService
{
    public function __construct(private DatabaseInterface $db) {}

    public function getSnapshot(int|string $member): array
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', 'com_decaromembership') && !$user->authorise('core.admin', 'com_decaromembership')) {
            throw new RuntimeException('Not authorised.', 403);
        }

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('person_uuid'),
                $this->db->quoteName('status'),
                $this->db->quoteName('voting_active'),
                $this->db->quoteName('voting_passive'),
                $this->db->quoteName('first_registration_date'),
                $this->db->quoteName('application_date'),
                $this->db->quoteName('admission_date'),
                $this->db->quoteName('cessation_date'),
            ])
            ->from($this->db->quoteName('#__decaromembership_members'))
            ->where($this->db->quoteName('published') . ' >= 0');

        if (is_int($member) || ctype_digit((string) $member)) {
            $id = (int) $member;
            $query->where($this->db->quoteName('id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
        } else {
            $uuid = strtolower(trim((string) $member));
            $query->where($this->db->quoteName('person_uuid') . ' = :uuid')->bind(':uuid', $uuid);
        }

        $row = $this->db->setQuery($query, 0, 1)->loadAssoc();
        if (!$row) {
            return [];
        }

        $active = (string) ($row['status'] ?? '') === 'active';
        $row['is_active_member'] = $active;
        $row['can_vote'] = $active && !empty($row['voting_active']);
        $row['can_be_candidate'] = $active && !empty($row['voting_passive']);

        return $row;
    }

    public function isFeeCurrent(int $memberId, string $associationYear): bool
    {
        if ($memberId < 1 || trim($associationYear) === '') {
            return false;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('status'))
            ->from($this->db->quoteName('#__decaromembership_dues'))
            ->where($this->db->quoteName('member_id') . ' = :member_id')
            ->where($this->db->quoteName('association_year') . ' = :year')
            ->where($this->db->quoteName('published') . ' >= 0')
            ->bind(':member_id', $memberId, ParameterType::INTEGER)
            ->bind(':year', $associationYear);

        $status = (string) $this->db->setQuery($query, 0, 1)->loadResult();
        return in_array($status, ['paid', 'waived'], true);
    }
}
