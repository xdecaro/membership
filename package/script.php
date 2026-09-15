<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class pkg_decaromembershipInstallerScript
{
    private const MINIMUM_CORE_VERSION = '2.0.1';
    private const MINIMUM_PEOPLE_VERSION = '1.2.15';

    public function preflight($type, $parent): bool
    {
        if (!in_array((string) $type, ['install', 'update', 'discover_install'], true)) {
            return true;
        }

        try {
            /** @var DatabaseInterface $db */
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $core = $this->installedVersion($db, 'package', 'pkg_xdecarocore');
            $people = $this->installedVersion($db, 'package', 'pkg_xdecaropeople');
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage('Membership 1.5.0 could not verify required xdecaro dependencies.', 'error');
            return false;
        }

        if ($core === null || version_compare($core, self::MINIMUM_CORE_VERSION, '<')) {
            Factory::getApplication()->enqueueMessage('Membership 1.5.0 requires Core by xdecaro 2.0.1 or newer.', 'error');
            return false;
        }
        if ($people === null || version_compare($people, self::MINIMUM_PEOPLE_VERSION, '<')) {
            Factory::getApplication()->enqueueMessage('Membership 1.5.0 requires People by xdecaro 1.2.15 or newer.', 'error');
            return false;
        }

        return true;
    }

    private function installedVersion(DatabaseInterface $db, string $type, string $element): ?string
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':type', $type)
            ->bind(':element', $element);

        $manifestCache = $db->setQuery($query, 0, 1)->loadResult();
        if (!is_string($manifestCache) || trim($manifestCache) === '') {
            return null;
        }

        $manifest = json_decode($manifestCache, true);
        $version = is_array($manifest) ? trim((string) ($manifest['version'] ?? '')) : '';

        return $version !== '' ? $version : null;
    }

    public function postflight($type, $parent): void
    {
        if (!in_array((string) $type, ['install', 'discover_install'], true)) { return; }
        try {
            /** @var DatabaseInterface $db */
            $db=Factory::getContainer()->get(DatabaseInterface::class);
            foreach ([['xdecaroanalytics','decaromembership'],['task','decaromembership']] as [$folder,$element]) {
                $enabled=1; $pluginType='plugin';
                $query=$db->getQuery(true)->update($db->quoteName('#__extensions'))->set($db->quoteName('enabled').' = :enabled')->where($db->quoteName('type').' = :type')->where($db->quoteName('folder').' = :folder')->where($db->quoteName('element').' = :element')->bind(':enabled',$enabled,ParameterType::INTEGER)->bind(':type',$pluginType)->bind(':folder',$folder)->bind(':element',$element);
                $db->setQuery($query)->execute();
            }
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage('Membership installed, but optional integration plugins could not be enabled automatically.','warning');
        }
    }
}
