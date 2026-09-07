<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Controller;

defined('_JEXEC') or die;
use Joomla\CMS\Factory; use Joomla\CMS\Language\Text; use Joomla\CMS\MVC\Controller\BaseController; use Joomla\CMS\Router\Route; use RuntimeException; use Throwable; use Xdecaro\Component\Decaromembership\Administrator\Helper\EntityRegistry;
final class RecordsController extends BaseController
{
    public function trash(): void
    {
        $this->checkToken(); $app=Factory::getApplication(); if(!$app->getIdentity()->authorise('core.delete','com_decaromembership')) throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);
        $entity=$this->input->getCmd('entity','members'); $entity=EntityRegistry::has($entity)?$entity:'members'; $ids=array_values(array_filter(array_map('intval',(array)$this->input->get('cid',[],'array'))));
        if(!$ids){$app->enqueueMessage(Text::_('JGLOBAL_NO_MATCHING_RESULTS'),'warning');} else { try{$model=$this->getModel('Record');$model->trashEntities($entity,$ids);$app->enqueueMessage(Text::sprintf('COM_DECAROMEMBERSHIP_TRASH_SUCCESS',count($ids)),'success');}catch(Throwable $e){$app->enqueueMessage($e->getMessage(),'error');} }
        $this->setRedirect(Route::_('index.php?option=com_decaromembership&view=records&entity='.$entity,false));
    }
    public function export(): void
    {
        $this->checkToken(); $app=Factory::getApplication(); if(!$app->getIdentity()->authorise('membership.export','com_decaromembership')) throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);
        $entity=$this->input->getCmd('entity','members'); $entity=EntityRegistry::has($entity)?$entity:'members'; $model=$this->getModel('Records'); $model->getState(); $model->setState('list.start',0); $model->setState('list.limit',0); $items=$model->getItems(); $config=EntityRegistry::get($entity); $columns=array_values(array_unique(array_merge(['id'],$config['list'])));
        $filename='membership-'.$entity.'-'.gmdate('Ymd-His').'.csv'; $app->setHeader('Content-Type','text/csv; charset=utf-8',true); $app->setHeader('Content-Disposition','attachment; filename="'.$filename.'"',true); $app->sendHeaders(); $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,$columns,';'); foreach($items as $item){$row=[];foreach($columns as $column)$row[]=$item->$column??'';fputcsv($out,$row,';');} fclose($out); $app->close();
    }
}
