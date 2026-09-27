<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$previewRows = (array) ($this->preview['rows'] ?? []);
$counts = (array) ($this->preview['counts'] ?? ['create' => 0, 'existing' => 0, 'invalid' => 0]);
$isPreview = ($this->step ?? 'select') === 'preview' && $previewRows !== [];
$initialPeople = (array) ($this->initialPeople ?? []);
$initialPeopleJson = json_encode(
    $initialPeople,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?: '[]';
?>
<div class="dm-page dm-cardbulk" data-cardbulk-root>
    <?php if (!$isPreview): ?>
    <div class="dm-card">
        <h2 class="h4 mb-2"><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_SETUP'); ?></h2>
        <p class="text-muted mb-3"><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_INTRO'); ?></p>
        <form action="<?= Route::_('index.php?option=com_decaromembership&task=cardbulk.preview'); ?>" method="post" id="cardbulkForm">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold" for="competition_season_id"><?= Text::_('COM_DECAROMEMBERSHIP_FIELD_COMPETITION_SEASON'); ?> *</label>
                    <select class="form-select" id="competition_season_id" name="competition_season_id" required>
                        <option value=""><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_SELECT_SEASON'); ?></option>
                        <?php foreach ($this->seasonOptions as $season): ?>
                            <option value="<?= (int) ($season['id'] ?? 0); ?>"<?= (int) ($season['id'] ?? 0) === $this->selectedSeasonId ? ' selected' : ''; ?>><?= $esc($season['label'] ?? $season['name'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <div class="d-flex flex-wrap gap-2 align-items-end">
                        <div class="flex-grow-1">
                            <label class="form-label fw-semibold" for="cardbulk_people_search"><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_PEOPLE'); ?></label>
                            <input class="form-control" type="search" id="cardbulk_people_search" data-cardbulk-search placeholder="<?= $esc(Text::_('COM_DECAROMEMBERSHIP_CARDBULK_SEARCH_PLACEHOLDER')); ?>">
                        </div>
                        <div style="min-width: 140px">
                            <label class="form-label fw-semibold" for="cardbulk_limit"><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_RESULTS'); ?></label>
                            <select class="form-select" id="cardbulk_limit" data-cardbulk-limit>
                                <option value="20">20</option>
                                <option value="50" selected>50</option>
                                <option value="100">100</option>
                                <option value="500">500</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-outline-primary" data-cardbulk-search-button><?= Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                    </div>
                </div>
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_SEARCH_RESULTS'); ?></strong>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-cardbulk-add-all disabled><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_ADD_RESULTS'); ?></button>
                    </div>
                    <div class="table-responsive border rounded">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_PERSON'); ?></th><th class="text-end"><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_ACTION'); ?></th></tr></thead>
                            <tbody data-cardbulk-results><tr><td colspan="2" class="text-muted py-3 text-center"><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_SEARCH_HINT'); ?></td></tr></tbody>
                        </table>
                    </div>
                </div>
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_SELECTED'); ?> <span class="badge text-bg-primary" data-cardbulk-count>0</span></strong>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-cardbulk-clear disabled><?= Text::_('JCLEAR'); ?></button>
                    </div>
                    <div class="border rounded p-2" data-cardbulk-selected>
                        <div class="text-muted text-center py-2" data-cardbulk-empty><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_NONE_SELECTED'); ?></div>
                    </div>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary" data-cardbulk-preview disabled><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_PREVIEW'); ?></button>
                <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_decaromembership&view=records&entity=cards'); ?>"><?= Text::_('JCANCEL'); ?></a>
            </div>
            <?= HTMLHelper::_('form.token'); ?>
        </form>
        <script type="application/json" data-cardbulk-initial-selection><?= $initialPeopleJson; ?></script>
    </div>
    <?php endif; ?>

    <?php if ($isPreview): ?>
        <div class="dm-card">
            <h2 class="h4 mb-3"><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_PREVIEW_TITLE'); ?></h2>
            <div class="row g-2 mb-3">
                <div class="col-md-4"><div class="border rounded p-3"><strong class="d-block fs-4"><?= (int) ($counts['create'] ?? 0); ?></strong><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_TO_CREATE'); ?></div></div>
                <div class="col-md-4"><div class="border rounded p-3"><strong class="d-block fs-4"><?= (int) ($counts['existing'] ?? 0); ?></strong><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_ALREADY_EXISTS'); ?></div></div>
                <div class="col-md-4"><div class="border rounded p-3"><strong class="d-block fs-4"><?= (int) ($counts['invalid'] ?? 0); ?></strong><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_INVALID'); ?></div></div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_PERSON'); ?></th><th><?= Text::_('JSTATUS'); ?></th><th><?= Text::_('COM_DECAROMEMBERSHIP_FIELD_CARD_NUMBER'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($previewRows as $row): $status=(string)($row['status']??'invalid'); ?>
                        <tr>
                            <td><?= $esc($row['label'] ?? $row['person_uuid'] ?? ''); ?></td>
                            <td><?php if($status==='create'): ?><span class="badge text-bg-primary"><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_STATUS_CREATE'); ?></span><?php elseif($status==='existing'): ?><span class="badge text-bg-success"><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_STATUS_EXISTING'); ?></span><?php else: ?><span class="badge text-bg-danger"><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_STATUS_INVALID'); ?></span><?php endif; ?></td>
                            <td><?= $esc($row['card_number'] ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_decaromembership&view=cardbulk&step=select&competition_season_id=' . (int) $this->selectedSeasonId); ?>"><?= Text::_('COM_DECAROMEMBERSHIP_CARDBULK_BACK'); ?></a>
                <?php if ((int) ($counts['create'] ?? 0) > 0): ?>
                    <form action="<?= Route::_('index.php?option=com_decaromembership&task=cardbulk.create'); ?>" method="post">
                        <button class="btn btn-success" type="submit"><?= Text::sprintf('COM_DECAROMEMBERSHIP_CARDBULK_CREATE_COUNT', (int) ($counts['create'] ?? 0)); ?></button>
                        <?= HTMLHelper::_('form.token'); ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
