<?php

namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use RuntimeException;
use Throwable;

final class PeopleIntegrationService
{
    public const MINIMUM_CORE_VERSION = '2.0.1';
    public const MINIMUM_PEOPLE_VERSION = '1.2.15';

    public function __construct(private DatabaseInterface $db)
    {
    }

    public function isAvailable(): bool
    {
        $status = $this->dependencyStatus();

        return $status['core_ok'] && $status['people_ok'];
    }

    public function dependencyStatus(): array
    {
        $core = $this->installedVersion('package', 'pkg_xdecarocore');
        $people = $this->installedVersion('package', 'pkg_xdecaropeople');

        return [
            'core_version' => $core,
            'people_version' => $people,
            'core_minimum' => self::MINIMUM_CORE_VERSION,
            'people_minimum' => self::MINIMUM_PEOPLE_VERSION,
            'core_ok' => $core !== null && version_compare($core, self::MINIMUM_CORE_VERSION, '>='),
            'people_ok' => $people !== null && version_compare($people, self::MINIMUM_PEOPLE_VERSION, '>='),
        ];
    }

    public function getPerson(string $uuid, bool $sensitive = false): ?array
    {
        $uuid = strtolower(trim($uuid));
        if ($uuid === '') {
            return null;
        }

        try {
            return $this->provider()->getPerson($uuid, $sensitive);
        } catch (Throwable $e) {
            throw new RuntimeException('People person lookup is unavailable: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function searchPeople(string $search, int $limit = 50): array
    {
        try {
            return (array) $this->provider()->searchPeople(['search' => trim($search)], $limit, false);
        } catch (Throwable $e) {
            throw new RuntimeException('People search is unavailable: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function getPeopleByUuids(array $uuids): array
    {
        try {
            return (array) $this->provider()->getPeopleByUuids($uuids, false);
        } catch (Throwable $e) {
            throw new RuntimeException('People batch lookup is unavailable: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function findByUserIdUnique(int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }

        try {
            $rows = (array) $this->provider()->searchPeople(['user_id' => $userId], 2, false);
        } catch (Throwable $e) {
            throw new RuntimeException('People user lookup is unavailable: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }

        return count($rows) === 1 ? $rows[0] : null;
    }

    public function openPersonUrl(string $uuid): string
    {
        $person = $this->getPerson($uuid, false);
        $id = (int) ($person['id'] ?? 0);

        return $id > 0
            ? 'index.php?option=com_xdecaropeople&task=person.edit&id=' . $id
            : 'index.php?option=com_xdecaropeople&view=people';
    }

    private function provider(): object
    {
        $status = $this->dependencyStatus();
        if (!$status['core_ok'] || !$status['people_ok']) {
            throw new RuntimeException(sprintf(
                'Membership requires Core %s+ and People %s+ for person integration.',
                self::MINIMUM_CORE_VERSION,
                self::MINIMUM_PEOPLE_VERSION
            ));
        }

        try {
            $component = Factory::getApplication()->bootComponent('com_xdecaropeople');
        } catch (Throwable $e) {
            throw new RuntimeException('People component could not be booted.', 0, $e);
        }

        if (!is_object($component) || !method_exists($component, 'getPersonProviderService')) {
            throw new RuntimeException('People public person provider is unavailable.');
        }

        $provider = $component->getPersonProviderService();
        if (!is_object($provider)
            || !method_exists($provider, 'getPerson')
            || !method_exists($provider, 'searchPeople')
            || !method_exists($provider, 'getPeopleByUuids')) {
            throw new RuntimeException('People public person provider is incompatible.');
        }

        return $provider;
    }

    private function installedVersion(string $type, string $element): ?string
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('manifest_cache'))
            ->from($this->db->quoteName('#__extensions'))
            ->where($this->db->quoteName('type') . ' = :type')
            ->where($this->db->quoteName('element') . ' = :element')
            ->bind(':type', $type)
            ->bind(':element', $element);

        $manifestCache = $this->db->setQuery($query, 0, 1)->loadResult();
        if (!is_string($manifestCache) || trim($manifestCache) === '') {
            return null;
        }

        $manifest = json_decode($manifestCache, true);
        $version = is_array($manifest) ? trim((string) ($manifest['version'] ?? '')) : '';

        return $version !== '' ? $version : null;
    }
}
