<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;
defined('_JEXEC') or die;
use Joomla\Database\DatabaseInterface;

final class RecordRepository
{
    public function __construct(private DatabaseInterface $db) {}

    public function load(string $table, int $id): ?object
    {
        $q = $this->db->getQuery(true)->select('*')->from($this->db->quoteName($table))->where($this->db->quoteName('id') . ' = :id')->bind(':id', $id);
        return $this->db->setQuery($q)->loadObject() ?: null;
    }

    public function save(string $table, int $id, array $data): int
    {
        if ($id > 0) {
            $set = [];
            $q = $this->db->getQuery(true)->update($this->db->quoteName($table));
            foreach ($data as $column => $value) {
                $ph = ':v_' . $column;
                $set[] = $this->db->quoteName($column) . ' = ' . $ph;
                $q->bind($ph, $value);
            }
            $q->set($set)->where($this->db->quoteName('id') . ' = :id')->bind(':id', $id);
            $this->db->setQuery($q)->execute();
            return $id;
        }

        $columns = array_keys($data);
        $values = [];
        $q = $this->db->getQuery(true)->insert($this->db->quoteName($table))->columns(array_map([$this->db, 'quoteName'], $columns));
        foreach ($data as $column => $value) {
            $ph = ':v_' . $column;
            $values[] = $ph;
            $q->bind($ph, $value);
        }
        $q->values(implode(',', $values));
        $this->db->setQuery($q)->execute();
        return (int) $this->db->insertid();
    }

    public function trash(string $table, int $id, string $modified, int $userId): void
    {
        $q = $this->db->getQuery(true)->update($this->db->quoteName($table))
            ->set($this->db->quoteName('published') . ' = -2')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->set($this->db->quoteName('modified_by') . ' = :uid')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':modified', $modified)->bind(':uid', $userId)->bind(':id', $id);
        $this->db->setQuery($q)->execute();
    }

    public function duplicateExists(string $table, string $column, mixed $value, int $excludeId = 0): bool
    {
        $q = $this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName($table))->where($this->db->quoteName($column) . ' = :value')->bind(':value', $value);
        if ($excludeId > 0) $q->where($this->db->quoteName('id') . ' <> :id')->bind(':id', $excludeId);
        return (int) $this->db->setQuery($q)->loadResult() > 0;
    }
}
