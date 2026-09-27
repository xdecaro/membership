<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Throwable;

final class CompetitionsIntegrationService
{
    public function isAvailable(): bool
    {
        if (!ComponentHelper::isEnabled('com_competitions')) {
            return false;
        }

        try {
            $component = Factory::getApplication()->bootComponent('com_competitions');
            return is_object($component) && method_exists($component, 'getSeasonDirectoryService');
        } catch (Throwable) {
            return false;
        }
    }

    public function listDclSeasons(): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        try {
            $component = Factory::getApplication()->bootComponent('com_competitions');
            if (!is_object($component) || !method_exists($component, 'getSeasonDirectoryService')) {
                return [];
            }
            $service = $component->getSeasonDirectoryService();
            return is_object($service) && method_exists($service, 'listForMembership')
                ? (array) $service->listForMembership()
                : [];
        } catch (Throwable $e) {
            Log::add('Membership Competitions bridge: ' . $e->getMessage(), Log::WARNING, 'com_decaromembership.integration');
            return [];
        }
    }

    public function getDclSeason(int $seasonId): ?array
    {
        if ($seasonId < 1 || !$this->isAvailable()) {
            return null;
        }

        try {
            $component = Factory::getApplication()->bootComponent('com_competitions');
            if (!is_object($component) || !method_exists($component, 'getSeasonDirectoryService')) {
                return null;
            }
            $service = $component->getSeasonDirectoryService();
            if (!is_object($service) || !method_exists($service, 'getForMembership')) {
                return null;
            }
            $season = $service->getForMembership($seasonId);
            return is_array($season) ? $season : null;
        } catch (Throwable $e) {
            Log::add('Membership Competitions bridge: ' . $e->getMessage(), Log::WARNING, 'com_decaromembership.integration');
            return null;
        }
    }
}
