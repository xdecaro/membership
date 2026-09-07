<?php
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
$app=\Joomla\CMS\Factory::getApplication(); $search=$app->input->getString('filter_search',''); $order=$app->input->getCmd('order','id'); $dir=strtoupper($app->input->getCmd('dir','DESC'))==='ASC'?'ASC':'DESC';
$nextDir=fn($col)=>$order===$col&&$dir==='ASC'?'DESC':'ASC';
$esc=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<form action="<?= Route::_('index.php?option=com_decaromembership&view=records&entity='.$this->entity) ?>" method="post" name="adminForm" id="adminForm">
<div class="dm-page"><div class="dm-listbar"><div class="dm-search"><label class="visually-hidden" for="filter_search"><?= Text::_('JSEARCH_FILTER') ?></label><input id="filter_search" name="filter_search" value="<?= $esc($search) ?>" placeholder="<?= Text::_('JSEARCH_FILTER') ?>"><button type="submit" class="btn btn-primary"><?= Text::_('JSEARCH_FILTER_SUBMIT') ?></button><a class="btn btn-outline-secondary" href="<?= Route::_('index.php?option=com_decaromembership&view=records&entity='.$this->entity) ?>"><?= Text::_('JSEARCH_FILTER_CLEAR') ?></a></div><a class="btn btn-success" href="<?= Route::_('index.php?option=com_decaromembership&view=record&entity='.$this->entity) ?>"><?= Text::_('JNEW') ?></a></div>
<div class="table-responsive dm-table-wrap"><table class="table table-striped align-middle"><thead><tr><th class="w-1"><?= HTMLHelper::_('grid.checkall') ?></th><?php foreach($this->config['list'] as $column): $field=$this->config['fields'][$column]??['label'=>$column]; ?><th><a href="<?= Route::_('index.php?option=com_decaromembership&view=records&entity='.$this->entity.'&filter_search='.rawurlencode($search).'&order='.$column.'&dir='.$nextDir($column)) ?>"><?= Text::_($field['label']) ?></a></th><?php endforeach; ?><th class="text-end">ID</th></tr></thead><tbody>
<?php if(!$this->items): ?><tr><td colspan="<?= count($this->config['list'])+2 ?>" class="text-center py-5"><?= Text::_('JGLOBAL_NO_MATCHING_RESULTS') ?></td></tr><?php endif; ?>
<?php foreach($this->items as $i=>$item): ?><tr><td><?= HTMLHelper::_('grid.id',$i,(int)$item->id) ?></td><?php foreach($this->config['list'] as $column): $value=$item->$column??''; ?><td><?php if($column===$this->config['title_field']): ?><a href="<?= Route::_('index.php?option=com_decaromembership&view=record&entity='.$this->entity.'&id='.(int)$item->id) ?>"><?= $esc($value) ?></a><?php elseif(isset($this->relationMaps[$column][(int)$value])): ?><?= $esc($this->relationMaps[$column][(int)$value]) ?><?php else: ?><?= $esc($value) ?><?php endif; ?></td><?php endforeach; ?><td class="text-end"><?= (int)$item->id ?></td></tr><?php endforeach; ?>
</tbody></table></div><div class="dm-pagination"><?= $this->pagination->getListFooter() ?></div></div>
<input type="hidden" name="task" value=""><input type="hidden" name="entity" value="<?= $esc($this->entity) ?>"><input type="hidden" name="boxchecked" value="0"><?= HTMLHelper::_('form.token') ?></form>
