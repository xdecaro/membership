<?php
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
$membershipAssetBase=rtrim(Uri::root(true),'/').'/media/com_decaromembership';
$membershipCssVersion=@filemtime(JPATH_ROOT.'/media/com_decaromembership/css/admin.css')?:'1.5.0';
$membershipJsVersion=@filemtime(JPATH_ROOT.'/media/com_decaromembership/js/admin.js')?:'1.5.0';
$app=\Joomla\CMS\Factory::getApplication();
$search=$app->input->getString('filter_search','');
$peopleLink=$this->entity==='members'?$app->input->getCmd('people_link','all'):'all';
$order=$app->input->getCmd('order',$this->entity==='members'?'member_number':'id');
$dir=strtoupper($app->input->getCmd('dir',$this->entity==='members'?'ASC':'DESC'))==='ASC'?'ASC':'DESC';
$nextDir=fn($col)=>$order===$col&&$dir==='ASC'?'DESC':'ASC';
$esc=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base='index.php?option=com_decaromembership&view=records&entity='.$this->entity;
?>
<link rel="stylesheet" href="<?= $esc($membershipAssetBase.'/css/admin.css?v='.$membershipCssVersion) ?>">
<form action="<?= Route::_($base) ?>" method="post" name="adminForm" id="adminForm">
<div class="dm-page">
  <div class="dm-listbar">
    <div class="dm-search">
      <label class="visually-hidden" for="filter_search"><?= Text::_('JSEARCH_FILTER') ?></label>
      <input id="filter_search" name="filter_search" value="<?= $esc($search) ?>" placeholder="<?= Text::_('JSEARCH_FILTER') ?>">
      <?php if($this->entity==='members'): ?>
        <label class="visually-hidden" for="people_link"><?= Text::_('COM_DECAROMEMBERSHIP_PEOPLE_LINK_FILTER') ?></label>
        <select id="people_link" name="people_link" class="form-select">
          <option value="all"<?= $peopleLink==='all'?' selected':'' ?>><?= Text::_('COM_DECAROMEMBERSHIP_PEOPLE_LINK_ALL') ?></option>
          <option value="linked"<?= $peopleLink==='linked'?' selected':'' ?>><?= Text::_('COM_DECAROMEMBERSHIP_PEOPLE_LINK_LINKED') ?></option>
          <option value="unlinked"<?= $peopleLink==='unlinked'?' selected':'' ?>><?= Text::_('COM_DECAROMEMBERSHIP_PEOPLE_LINK_UNLINKED') ?></option>
        </select>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary"><?= Text::_('JSEARCH_FILTER_SUBMIT') ?></button>
      <a class="btn btn-outline-secondary" href="<?= Route::_($base) ?>"><?= Text::_('JSEARCH_FILTER_CLEAR') ?></a>
    </div>
  </div>
  <div class="table-responsive dm-table-wrap">
    <table class="table table-striped align-middle">
      <thead><tr><th class="w-1"><?= HTMLHelper::_('grid.checkall') ?></th>
      <?php foreach($this->config['list'] as $column):
          $field=$this->config['fields'][$column]??['label'=>$column];
          $canSort=!($this->entity==='members'&&in_array($column,['first_name','last_name'],true));
          $sortUrl=$base.'&filter_search='.rawurlencode($search).'&people_link='.rawurlencode($peopleLink).'&order='.$column.'&dir='.$nextDir($column);
      ?>
        <th><?php if($canSort): ?><a href="<?= Route::_($sortUrl) ?>"><?= Text::_($field['label']) ?></a><?php else: ?><?= Text::_($field['label']) ?><?php endif; ?></th>
      <?php endforeach; ?><th class="text-end">ID</th></tr></thead>
      <tbody>
      <?php if(!$this->items): ?><tr><td colspan="<?= count($this->config['list'])+2 ?>" class="text-center py-5"><?= Text::_('JGLOBAL_NO_MATCHING_RESULTS') ?></td></tr><?php endif; ?>
      <?php foreach($this->items as $i=>$item):
          $personUuid=strtolower(trim((string)($item->person_uuid??'')));
          $person=$personUuid!==''?($this->peopleMap[$personUuid]??null):null;
          $linked=$personUuid!=='';
      ?>
        <tr><td><?= HTMLHelper::_('grid.id',$i,(int)$item->id) ?></td>
        <?php foreach($this->config['list'] as $column):
            $value=$item->$column??'';
            $identityBadge='';
            if($this->entity==='members'&&in_array($column,['first_name','last_name'],true)) {
                if($person) {
                    $value=$column==='first_name'?($person['first_name']??''):($person['last_name']??($person['display_name']??''));
                    if($column==='last_name') $identityBadge='<span class="badge text-bg-success ms-1">'.$esc(Text::_('COM_DECAROMEMBERSHIP_PEOPLE_LINKED')).'</span>';
                } elseif($linked) {
                    $value=$column==='last_name'?(($item->member_number??'')!==''?$item->member_number:'#'.(int)$item->id):'';
                    if($column==='last_name') $identityBadge='<span class="badge text-bg-warning ms-1">'.$esc(Text::_('COM_DECAROMEMBERSHIP_PEOPLE_UNAVAILABLE')).'</span>';
                } else {
                    if($column==='last_name') $identityBadge='<span class="badge text-bg-secondary ms-1">'.$esc(Text::_('COM_DECAROMEMBERSHIP_PEOPLE_UNLINKED')).'</span>';
                }
            }
        ?>
          <td>
          <?php if($column===$this->config['title_field']): ?>
            <a href="<?= Route::_('index.php?option=com_decaromembership&view=record&entity='.$this->entity.'&id='.(int)$item->id) ?>"<?= $person?' title="'.$esc($person['display_name']??'').'"':'' ?>><?= $esc($value) ?></a><?= $identityBadge ?>
          <?php elseif(isset($this->relationMaps[$column][(int)$value])): ?><?= $esc($this->relationMaps[$column][(int)$value]) ?>
          <?php else: ?><?= $esc($value) ?><?= $identityBadge ?><?php endif; ?>
          </td>
        <?php endforeach; ?><td class="text-end"><?= (int)$item->id ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="dm-pagination"><?= $this->pagination->getListFooter() ?></div>
</div>
<input type="hidden" name="task" value=""><input type="hidden" name="entity" value="<?= $esc($this->entity) ?>"><input type="hidden" name="boxchecked" value="0"><?= HTMLHelper::_('form.token') ?></form>
<script src="<?= $esc($membershipAssetBase.'/js/admin.js?v='.$membershipJsVersion) ?>"></script>
