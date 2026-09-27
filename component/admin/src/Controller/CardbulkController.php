<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use RuntimeException;
use Throwable;

final class CardbulkController extends BaseController
{
    private const STATE_KEY = 'com_decaromembership.cardbulk.preview';

    public function preview(): void
    {
        $this->checkToken();
        $app = Factory::getApplication();
        if (!$app->getIdentity()->authorise('core.create', 'com_decaromembership')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $seasonId = $this->input->post->getInt('competition_season_id', 0);
        $uuids = array_values(array_unique(array_filter(array_map(
            static fn($value): string => strtolower(trim((string) $value)),
            (array) $this->input->post->get('person_uuids', [], 'array')
        ))));

        if ($seasonId < 1 || $uuids === []) {
            $app->enqueueMessage(Text::_('COM_DECAROMEMBERSHIP_CARDBULK_SELECT_REQUIRED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_decaromembership&view=cardbulk&step=select&competition_season_id=' . $seasonId, false));
            return;
        }

        try {
            $preview = $this->getModel('Cardbulk')->previewBatch($uuids, $seasonId);
            if (!empty($preview['error'])) {
                throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_CARDBULK_SEASON_INVALID'));
            }
            $preview['competition_season_id'] = $seasonId;
            $app->setUserState(self::STATE_KEY, $preview);
        } catch (Throwable $e) {
            $app->setUserState(self::STATE_KEY, null);
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_decaromembership&view=cardbulk&step=preview&competition_season_id=' . $seasonId, false));
    }

    public function create(): void
    {
        $this->checkToken();
        $app = Factory::getApplication();
        if (!$app->getIdentity()->authorise('core.create', 'com_decaromembership')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $preview = (array) $app->getUserState(self::STATE_KEY, []);
        $seasonId = (int) ($preview['competition_season_id'] ?? 0);
        $uuids = [];
        foreach ((array) ($preview['rows'] ?? []) as $row) {
            if (($row['status'] ?? '') === 'create' && !empty($row['person_uuid'])) {
                $uuids[] = (string) $row['person_uuid'];
            }
        }

        if ($seasonId < 1 || $uuids === []) {
            $app->enqueueMessage(Text::_('COM_DECAROMEMBERSHIP_CARDBULK_PREVIEW_EXPIRED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_decaromembership&view=cardbulk&step=select', false));
            return;
        }

        try {
            $result = $this->getModel('Cardbulk')->createBatch($uuids, $seasonId);
            $app->enqueueMessage(
                Text::sprintf(
                    'COM_DECAROMEMBERSHIP_CARDBULK_RESULT',
                    (int) ($result['created'] ?? 0),
                    (int) ($result['existing'] ?? 0),
                    (int) ($result['failed'] ?? 0)
                ),
                (int) ($result['failed'] ?? 0) > 0 ? 'warning' : 'success'
            );
            $app->setUserState(self::STATE_KEY, null);
            $this->setRedirect(Route::_('index.php?option=com_decaromembership&view=records&entity=cards', false));
        } catch (Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_decaromembership&view=cardbulk&step=preview&competition_season_id=' . $seasonId, false));
        }
    }

    public function reset(): void
    {
        $this->checkToken('get');
        Factory::getApplication()->setUserState(self::STATE_KEY, null);
        $this->setRedirect(Route::_('index.php?option=com_decaromembership&view=cardbulk', false));
    }
}
