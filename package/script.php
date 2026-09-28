<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Xdecaro\Component\Decaromembership\Administrator\Service\AuditService;
use Xdecaro\Component\Decaromembership\Administrator\Service\MemberPeopleBackfillService;
use Xdecaro\Component\Decaromembership\Administrator\Service\RecordRepository;

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
            $core = $this->installedPackageVersion($db, ['pkg_core', 'pkg_xdecarocore']);
            $people = $this->installedPackageVersion($db, ['pkg_people', 'pkg_xdecaropeople']);
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage('Membership could not verify required xdecaro dependencies.', 'error');
            return false;
        }

        if ($core === null || version_compare($core, self::MINIMUM_CORE_VERSION, '<')) {
            Factory::getApplication()->enqueueMessage('Membership requires Core by xdecaro 2.0.1 or newer.', 'error');
            return false;
        }
        if ($people === null || version_compare($people, self::MINIMUM_PEOPLE_VERSION, '<')) {
            Factory::getApplication()->enqueueMessage('Membership requires People by xdecaro 1.2.15 or newer.', 'error');
            return false;
        }

        return true;
    }

    private function installedPackageVersion(DatabaseInterface $db, array $elements): ?string
    {
        $versions = [];
        foreach ($elements as $element) {
            $version = $this->installedVersion($db, 'package', (string) $element);
            if ($version !== null) {
                $versions[] = $version;
            }
        }

        if ($versions === []) {
            return null;
        }

        usort($versions, 'version_compare');
        return (string) end($versions);
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
        $type = (string) $type;
        if (!in_array($type, ['install', 'update', 'discover_install'], true)) {
            return;
        }

        $app = Factory::getApplication();
        $identity = $app->getIdentity();
        $memberCount = null;

        $requiredAssets = [
            JPATH_ROOT . '/media/com_decaromembership/css/admin.css',
            JPATH_ROOT . '/media/com_decaromembership/css/core-bridge.css',
            JPATH_ROOT . '/media/com_decaromembership/js/admin.js',
            JPATH_ROOT . '/media/com_decaromembership/joomla.asset.json',
        ];
        $missingAssets = array_values(array_filter(
            $requiredAssets,
            static fn (string $path): bool => !is_file($path)
        ));
        if ($missingAssets !== []) {
            $app->enqueueMessage(
                'Membership media assets are missing after installation. Reinstall the package and verify write permissions for /media/com_decaromembership.',
                'error'
            );
        }

        try {
            /** @var DatabaseInterface $db */
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__decaromembership_members'));
            $memberCount = (int) $db->setQuery($query)->loadResult();
        } catch (\Throwable $e) {
            $app->enqueueMessage(
                'Membership installed or updated, but existing members could not be inspected for People backfill.',
                'warning'
            );
        }

        if ($memberCount !== null && $memberCount > 0) {
            if ($identity && (int) $identity->id > 0) {
                try {
                    /** @var DatabaseInterface $db */
                    $db = Factory::getContainer()->get(DatabaseInterface::class);
                    $component = $app->bootComponent('com_decaromembership');
                    if (!method_exists($component, 'getPeopleIntegrationService')) {
                        throw new \RuntimeException('Membership People integration service is unavailable.');
                    }

                    $backfill = new MemberPeopleBackfillService(
                        new RecordRepository($db),
                        $component->getPeopleIntegrationService(),
                        new AuditService($db)
                    );
                    $result = $backfill->run((int) $identity->id, Factory::getDate()->toSql());

                    if (($result['linked'] ?? 0) > 0) {
                        $app->enqueueMessage(
                            'Membership people_backfill linked ' . (int) $result['linked'] . ' legacy member(s) to People.',
                            'message'
                        );
                    }
                } catch (\Throwable $e) {
                    $app->enqueueMessage(
                        'Membership installed or updated, but the People backfill could not be completed automatically.',
                        'warning'
                    );
                }
            } else {
                $app->enqueueMessage(
                    'Membership People backfill deferred because the installer has no authenticated Joomla identity.',
                    'warning'
                );
            }
        }

        try {
            /** @var DatabaseInterface $db */
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $component = $app->bootComponent('com_decaromembership');
            $updatedCards = $this->reconcileCompetitionCardDates(
                $db,
                $component,
                $identity ? (int) $identity->id : 0
            );
            if ($updatedCards > 0) {
                $app->enqueueMessage(
                    'Membership synchronized ' . $updatedCards . ' competition card(s) with current season dates.',
                    'message'
                );
            }
        } catch (\Throwable $e) {
            $app->enqueueMessage(
                'Membership updated, but existing competition-card dates could not be reconciled automatically.',
                'warning'
            );
        }

        try {
            /** @var DatabaseInterface $db */
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $plugins = [['system','decaromembership']];
            if (in_array($type, ['install', 'discover_install'], true)) {
                $plugins[] = ['xdecaroanalytics','decaromembership'];
                $plugins[] = ['task','decaromembership'];
            }

            foreach ($plugins as [$folder,$element]) {
                $enabled=1; $pluginType='plugin';
                $query=$db->getQuery(true)->update($db->quoteName('#__extensions'))->set($db->quoteName('enabled').' = :enabled')->where($db->quoteName('type').' = :type')->where($db->quoteName('folder').' = :folder')->where($db->quoteName('element').' = :element')->bind(':enabled',$enabled,ParameterType::INTEGER)->bind(':type',$pluginType)->bind(':folder',$folder)->bind(':element',$element);
                $db->setQuery($query)->execute();
            }
        } catch (\Throwable $e) {
            $app->enqueueMessage('Membership installed or updated, but integration plugins could not be enabled automatically.','warning');
        }
    }
    private function reconcileCompetitionCardDates(DatabaseInterface $db, object $component, int $actorUserId): int
    {
        if (!method_exists($component, 'getCompetitionsIntegrationService') || !method_exists($component, 'getDclCardService')) {
            return 0;
        }

        $competitions = $component->getCompetitionsIntegrationService();
        if (!is_object($competitions) || !method_exists($competitions, 'isAvailable') || !$competitions->isAvailable()) {
            return 0;
        }

        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('external_entity_id'))
            ->from($db->quoteName('#__decaromembership_entity_links'))
            ->where($db->quoteName('local_entity_type') . ' = ' . $db->quote('cards'))
            ->where($db->quoteName('component') . ' = ' . $db->quote('com_competitions'))
            ->where($db->quoteName('external_entity_type') . ' = ' . $db->quote('season'))
            ->where($db->quoteName('relation_type') . ' IN ('
                . $db->quote('card_competition_context') . ', '
                . $db->quote('dcl_card_season') . ')');

        $seasonIds = array_values(array_unique(array_map('intval', (array) $db->setQuery($query)->loadColumn())));
        $seasonIds = array_values(array_filter($seasonIds, static fn (int $id): bool => $id > 0));
        if ($seasonIds === []) {
            return 0;
        }

        $updated = 0;
        $nowSql = Factory::getDate()->toSql();
        $cardService = $component->getDclCardService();

        foreach ($seasonIds as $seasonId) {
            try {
                $season = $competitions->getDclSeason($seasonId);
                if (!is_array($season)) {
                    continue;
                }

                $result = $cardService->syncCompetitionSeasonDates(
                    $seasonId,
                    isset($season['start_date']) ? (string) $season['start_date'] : null,
                    isset($season['end_date']) ? (string) $season['end_date'] : null,
                    max(0, $actorUserId),
                    $nowSql
                );
                $updated += (int) ($result['cards_updated'] ?? 0);
            } catch (\Throwable) {
                // One optional external season must not block the package upgrade or other seasons.
            }
        }

        return $updated;
    }

}
