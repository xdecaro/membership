<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Xdecaro\Component\Decaromembership\Administrator\Service\CompetitionsIntegrationService;
use Xdecaro\Component\Decaromembership\Administrator\Service\DclCardService;

final class CardbulkModel extends BaseDatabaseModel
{
    public function getSeasonOptions(): array
    {
        return (new CompetitionsIntegrationService())->listDclSeasons();
    }

    public function getSeason(int $seasonId): ?array
    {
        return $seasonId > 0 ? (new CompetitionsIntegrationService())->getDclSeason($seasonId) : null;
    }

    public function previewBatch(array $personUuids, int $seasonId): array
    {
        return (new DclCardService($this->getDatabase()))->previewCompetitionBatch($personUuids, $seasonId);
    }

    public function createBatch(array $personUuids, int $seasonId): array
    {
        $identity = Factory::getApplication()->getIdentity();
        return (new DclCardService($this->getDatabase()))->createCompetitionBatch(
            $personUuids,
            $seasonId,
            (int) $identity->id,
            Factory::getDate()->toSql()
        );
    }
}
