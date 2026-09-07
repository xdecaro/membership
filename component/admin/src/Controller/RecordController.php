<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use RuntimeException;
use Throwable;
use Xdecaro\Component\Decaromembership\Administrator\Helper\EntityRegistry;

final class RecordController extends BaseController
{
    private function entity(): string { $entity=$this->input->getCmd('entity','members'); return EntityRegistry::has($entity)?$entity:'members'; }
    public function add(): void { $entity=$this->entity(); if(!Factory::getApplication()->getIdentity()->authorise('core.create','com_decaromembership')) throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403); $this->setRedirect(Route::_('index.php?option=com_decaromembership&view=record&entity='.$entity,false)); }
    public function edit(): void { $entity=$this->entity(); if(!Factory::getApplication()->getIdentity()->authorise('core.edit','com_decaromembership')) throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403); $cid=(array)$this->input->get('cid',[],'array'); $id=(int)($cid[0]??$this->input->getInt('id')); $this->setRedirect(Route::_('index.php?option=com_decaromembership&view=record&entity='.$entity.'&id='.$id,false)); }
    public function save(): void { $this->saveInternal(false); }
    public function apply(): void { $this->saveInternal(true); }
    private function saveInternal(bool $apply): void
    {
        $this->checkToken(); $app=Factory::getApplication(); $entity=$this->entity(); $id=$this->input->getInt('id'); $permission=$id>0?'core.edit':'core.create';
        if(!$app->getIdentity()->authorise($permission,'com_decaromembership')) throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);
        try { $model=$this->getModel('Record'); $data=(array)$this->input->get('jform',[],'array'); $savedId=$model->saveEntity($entity,$id,$data); $app->enqueueMessage(Text::_('COM_DECAROMEMBERSHIP_SAVE_SUCCESS'),'success'); $url='index.php?option=com_decaromembership&view='.($apply?'record':'records').'&entity='.$entity; if($apply)$url.='&id='.$savedId; $this->setRedirect(Route::_($url,false)); }
        catch(Throwable $e){ $app->enqueueMessage($e->getMessage(),'error'); $this->setRedirect(Route::_('index.php?option=com_decaromembership&view=record&entity='.$entity.'&id='.$id,false)); }
    }
    public function cancel(): void { $entity=$this->entity(); $this->setRedirect(Route::_('index.php?option=com_decaromembership&view=records&entity='.$entity,false)); }
}
