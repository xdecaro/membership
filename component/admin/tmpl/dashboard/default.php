<?php
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
$cards=['members'=>'COM_DECAROMEMBERSHIP_MEMBERS','cases'=>'COM_DECAROMEMBERSHIP_CASES','renewals'=>'COM_DECAROMEMBERSHIP_RENEWALS','cards'=>'COM_DECAROMEMBERSHIP_CARDS','dues'=>'COM_DECAROMEMBERSHIP_DUES','payments'=>'COM_DECAROMEMBERSHIP_PAYMENTS','transfers'=>'COM_DECAROMEMBERSHIP_TRANSFERS','documents'=>'COM_DECAROMEMBERSHIP_DOCUMENTS'];
?>
<div class="dm-page">
  <div class="dm-hero"><div><h1><?= Text::_('COM_DECAROMEMBERSHIP_DASHBOARD') ?></h1><p><?= Text::_('COM_DECAROMEMBERSHIP_DASHBOARD_DESC') ?></p></div></div>
  <div class="dm-grid dm-grid-kpi">
    <?php foreach($cards as $entity=>$label): ?><a class="dm-card dm-kpi" href="<?= Route::_('index.php?option=com_decaromembership&view=records&entity='.$entity) ?>"><span class="dm-kpi-value"><?= (int)($this->data['counts'][$entity]??0) ?></span><span><?= Text::_($label) ?></span></a><?php endforeach; ?>
  </div>
  <div class="dm-section"><div class="dm-section-head"><h2><?= Text::_('COM_DECAROMEMBERSHIP_MANAGEMENT_AREAS') ?></h2></div><div class="dm-grid dm-grid-actions">
  <?php foreach(['categories','case_statuses','locations','relations','checklist_templates','notifications'] as $entity): $cfg=\Xdecaro\Component\Decaromembership\Administrator\Helper\EntityRegistry::get($entity); ?><a class="dm-card dm-action" href="<?= Route::_('index.php?option=com_decaromembership&view=records&entity='.$entity) ?>"><strong><?= Text::_($cfg['label']) ?></strong><span><?= Text::_('COM_DECAROMEMBERSHIP_OPEN_AREA') ?></span></a><?php endforeach; ?>
  </div></div>
  <div class="dm-section"><div class="dm-section-head"><h2><?= Text::_('COM_DECAROMEMBERSHIP_INTEGRATIONS') ?></h2></div><div class="dm-integration-list"><?php foreach($this->data['integrations'] as $integration): ?><div class="dm-row"><span><?= htmlspecialchars($integration['name'],ENT_QUOTES,'UTF-8') ?></span><span class="dm-badge <?= $integration['enabled']?'is-ok':'is-muted' ?>"><?= Text::_($integration['enabled']?'JENABLED':'JDISABLED') ?></span></div><?php endforeach; ?></div></div>
</div>
