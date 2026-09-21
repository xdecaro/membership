<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;
defined('_JEXEC') or die;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class RecordRepository
{
    public function __construct(private DatabaseInterface $db) {}

    public function load(string $table, int $id): ?object
    {
        $q = $this->db->getQuery(true)->select('*')->from($this->db->quoteName($table))->where($this->db->quoteName('id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
        return $this->db->setQuery($q)->loadObject() ?: null;
    }

    public function save(string $table, int $id, array $data): int
    {
        $bindings = [];

        if ($id > 0) {
            $set = [];
            $q = $this->db->getQuery(true)->update($this->db->quoteName($table));
            foreach ($data as $column => $value) {
                $ph = ':v_' . $column;
                $set[] = $this->db->quoteName($column) . ' = ' . $ph;
                $bindings[$ph] = $value;
                $q->bind($ph, $bindings[$ph], $this->bindType($value));
            }
            $q->set($set)->where($this->db->quoteName('id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
            $this->db->setQuery($q)->execute();
            return $id;
        }

        $columns = array_keys($data);
        $values = [];
        $q = $this->db->getQuery(true)->insert($this->db->quoteName($table))->columns(array_map([$this->db, 'quoteName'], $columns));
        foreach ($data as $column => $value) {
            $ph = ':v_' . $column;
            $values[] = $ph;
            $bindings[$ph] = $value;
            $q->bind($ph, $bindings[$ph], $this->bindType($value));
        }
        $q->values(implode(',', $values));
        $this->db->setQuery($q)->execute();
        return (int) $this->db->insertid();
    }

    private function bindType(mixed $value): string
    {
        return match (true) {
            $value === null => ParameterType::NULL,
            is_bool($value) => ParameterType::BOOLEAN,
            is_int($value) => ParameterType::INTEGER,
            default => ParameterType::STRING,
        };
    }

    public function trash(string $table, int $id, string $modified, int $userId): void
    {
        $q = $this->db->getQuery(true)->update($this->db->quoteName($table))
            ->set($this->db->quoteName('published') . ' = -2')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->set($this->db->quoteName('modified_by') . ' = :uid')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':modified', $modified)->bind(':uid', $userId, ParameterType::INTEGER)->bind(':id', $id, ParameterType::INTEGER);
        $this->db->setQuery($q)->execute();
    }

    public function duplicateExists(string $table, string $column, mixed $value, int $excludeId = 0): bool
    {
        $q = $this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName($table))->where($this->db->quoteName($column) . ' = :value')->bind(':value', $value, $this->bindType($value));
        if ($excludeId > 0) $q->where($this->db->quoteName('id') . ' <> :id')->bind(':id', $excludeId, ParameterType::INTEGER);
        return (int) $this->db->setQuery($q)->loadResult() > 0;
    }

    public function renewalDuplicateExists(int $memberId, string $associationYear, int $excludeId = 0): bool
    {
        if ($memberId < 1 || trim($associationYear) === '') {
            return false;
        }

        $year = trim($associationYear);
        $q = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__decaromembership_renewals'))
            ->where($this->db->quoteName('member_id') . ' = :member_id')
            ->where($this->db->quoteName('association_year') . ' = :association_year')
            ->bind(':member_id', $memberId, ParameterType::INTEGER)
            ->bind(':association_year', $year);

        if ($excludeId > 0) {
            $q->where($this->db->quoteName('id') . ' <> :exclude_id')
                ->bind(':exclude_id', $excludeId, ParameterType::INTEGER);
        }

        return (int) $this->db->setQuery($q)->loadResult() > 0;
    }

    public function findMemberIdByPersonUuid(string $uuid, int $excludeId = 0): ?int
    {
        $q = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__decaromembership_members'))
            ->where($this->db->quoteName('person_uuid') . ' = :uuid')
            ->bind(':uuid', $uuid);
        if ($excludeId > 0) {
            $q->where($this->db->quoteName('id') . ' <> :exclude_id')->bind(':exclude_id', $excludeId, ParameterType::INTEGER);
        }
        $id = $this->db->setQuery($q, 0, 1)->loadResult();

        return $id === null ? null : (int) $id;
    }

    public function updateMemberPersonUuid(int $memberId, string $uuid, string $modified, int $userId): void
    {
        $q = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__decaromembership_members'))
            ->set($this->db->quoteName('person_uuid') . ' = :uuid')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->set($this->db->quoteName('modified_by') . ' = :user_id')
            ->where($this->db->quoteName('id') . ' = :member_id')
            ->bind(':uuid', $uuid)
            ->bind(':modified', $modified)
            ->bind(':user_id', $userId, ParameterType::INTEGER)
            ->bind(':member_id', $memberId, ParameterType::INTEGER);
        $this->db->setQuery($q)->execute();
    }

    public function loadUnlinkedMembersWithUserId(): array
    {
        $q = $this->db->getQuery(true)
            ->select([$this->db->quoteName('id'), $this->db->quoteName('user_id')])
            ->from($this->db->quoteName('#__decaromembership_members'))
            ->where($this->db->quoteName('person_uuid') . ' IS NULL')
            ->where($this->db->quoteName('user_id') . ' IS NOT NULL')
            ->where($this->db->quoteName('user_id') . ' > 0')
            ->order($this->db->quoteName('id') . ' ASC');

        return (array) $this->db->setQuery($q)->loadObjectList();
    }

    public function updateMemberNumber(int $memberId, string $memberNumber, string $modified, int $userId): void
    {
        if ($memberId < 1 || trim($memberNumber) === '') {
            return;
        }

        $q = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__decaromembership_members'))
            ->set($this->db->quoteName('member_number') . ' = :member_number')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->set($this->db->quoteName('modified_by') . ' = :user_id')
            ->where($this->db->quoteName('id') . ' = :member_id')
            ->bind(':member_number', $memberNumber)
            ->bind(':modified', $modified)
            ->bind(':user_id', $userId, ParameterType::INTEGER)
            ->bind(':member_id', $memberId, ParameterType::INTEGER);
        $this->db->setQuery($q)->execute();
    }

    public function loadCurrentMemberCard(int $memberId): ?object
    {
        if ($memberId < 1) {
            return null;
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__decaromembership_cards'))
            ->where($this->db->quoteName('member_id') . ' = :member_id')
            ->where($this->db->quoteName('published') . ' = 1')
            ->order(
                'CASE WHEN ' . $this->db->quoteName('status') . " = 'active' THEN 0 ELSE 1 END ASC"
            )
            ->order('COALESCE(' . $this->db->quoteName('activated_at') . ', ' . $this->db->quoteName('issued_at') . ", '0000-00-00') DESC")
            ->order($this->db->quoteName('id') . ' DESC')
            ->bind(':member_id', $memberId, ParameterType::INTEGER);

        return $this->db->setQuery($q, 0, 1)->loadObject() ?: null;
    }

    public function updateMemberLocation(int $memberId, int $locationId, string $modified, int $userId): void
    {
        if ($memberId < 1 || $locationId < 1) {
            return;
        }

        $q = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__decaromembership_members'))
            ->set($this->db->quoteName('location_id') . ' = :location_id')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->set($this->db->quoteName('modified_by') . ' = :user_id')
            ->where($this->db->quoteName('id') . ' = :member_id')
            ->bind(':location_id', $locationId, ParameterType::INTEGER)
            ->bind(':modified', $modified)
            ->bind(':user_id', $userId, ParameterType::INTEGER)
            ->bind(':member_id', $memberId, ParameterType::INTEGER);
        $this->db->setQuery($q)->execute();
    }

    public function updateMemberOrganization(int $memberId, string $organizationUuid, string $modified, int $userId): void
    {
        $organizationUuid = strtolower(trim($organizationUuid));

        if ($memberId < 1 || $organizationUuid === '') {
            return;
        }

        $q = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__decaromembership_members'))
            ->set($this->db->quoteName('organization_uuid') . ' = :organization_uuid')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->set($this->db->quoteName('modified_by') . ' = :user_id')
            ->where($this->db->quoteName('id') . ' = :member_id')
            ->bind(':organization_uuid', $organizationUuid)
            ->bind(':modified', $modified)
            ->bind(':user_id', $userId, ParameterType::INTEGER)
            ->bind(':member_id', $memberId, ParameterType::INTEGER);

        $this->db->setQuery($q)->execute();
    }
}
