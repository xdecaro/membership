<?php
namespace Xdecaro\Component\Decaromembership\Administrator\View\Cardbulk;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Xdecaro\Component\Decaromembership\Administrator\Service\AdminAssetService;

final class HtmlView extends BaseHtmlView
{
    public array $seasonOptions = [];
    public int $selectedSeasonId = 0;
    public ?array $selectedSeason = null;
    public array $preview = [];
    public string $step = 'select';
    public array $initialPeople = [];

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        if (!$app->getIdentity()->authorise('core.create', 'com_decaromembership')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $model = $this->getModel();
        $this->seasonOptions = $model->getSeasonOptions();
        $this->preview = (array) $app->getUserState('com_decaromembership.cardbulk.preview', []);
        $requestedStep = strtolower($app->input->getCmd('step', ''));
        $hasPreview = !empty($this->preview['rows']);
        $this->step = $requestedStep === 'preview' && $hasPreview ? 'preview' : 'select';
        $stateSeasonId = in_array($requestedStep, ['select', 'preview'], true)
            ? (int) ($this->preview['competition_season_id'] ?? 0)
            : 0;
        $this->selectedSeasonId = $app->input->getInt('competition_season_id', $stateSeasonId);

        if ($requestedStep === 'select' && $hasPreview) {
            foreach ((array) ($this->preview['rows'] ?? []) as $row) {
                $uuid = strtolower(trim((string) ($row['person_uuid'] ?? '')));
                if ($uuid === '') {
                    continue;
                }

                $this->initialPeople[$uuid] = [
                    'uuid' => $uuid,
                    'display_name' => trim((string) ($row['label'] ?? $uuid)) ?: $uuid,
                ];
            }
            $this->initialPeople = array_values($this->initialPeople);
        }
        $this->selectedSeason = $this->selectedSeasonId > 0 ? $model->getSeason($this->selectedSeasonId) : null;

        AdminAssetService::useAssets($this->getDocument());
        $base = rtrim(Uri::root(true), '/') . '/media/com_decaromembership';
        $this->getDocument()->addScript($base . '/js/cardbulk.js', ['version' => '1.9.31'], ['defer' => true]);

        ToolbarHelper::title(Text::_('COM_DECAROMEMBERSHIP_CARDBULK_TITLE'), 'copy');
        ToolbarHelper::link('index.php?option=com_decaromembership&view=records&entity=cards', Text::_('JTOOLBAR_CLOSE'), 'arrow-left');
        parent::display($tpl);
    }
}
