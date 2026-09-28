<?php
namespace Xdecaro\Plugin\System\Decaromembership\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Throwable;

final class Decaromembership extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onXdecaroCompetitionSeasonDatesChanged' => 'onXdecaroCompetitionSeasonDatesChanged',
        ];
    }

    public function onXdecaroCompetitionSeasonDatesChanged(Event $event): void
    {
        try {
            $seasonId = (int) $event->getArgument('season_id', 0);
            if ($seasonId < 1) {
                return;
            }

            $component = Factory::getApplication()->bootComponent('com_decaromembership');
            if (!method_exists($component, 'getDclCardService') || !method_exists($component, 'getCompetitionsIntegrationService')) {
                return;
            }

            $season = $component->getCompetitionsIntegrationService()->getDclSeason($seasonId);
            if (!is_array($season)) {
                return;
            }

            $component->getDclCardService()->syncCompetitionSeasonDates(
                $seasonId,
                isset($season['start_date']) ? (string) $season['start_date'] : null,
                isset($season['end_date']) ? (string) $season['end_date'] : null,
                max(0, (int) $event->getArgument('actor_user_id', 0)),
                Factory::getDate()->toSql()
            );
        } catch (Throwable $e) {
            Log::add(
                'Membership could not synchronize competition-card dates after a season update: ' . $e->getMessage(),
                Log::WARNING,
                'com_decaromembership'
            );
        }
    }
}
