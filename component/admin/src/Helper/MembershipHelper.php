<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Helper;
defined('_JEXEC') or die;
use Joomla\CMS\Component\ComponentHelper; use Joomla\CMS\Factory; use Joomla\Database\DatabaseInterface; use Throwable;
final class MembershipHelper
{
    public static function integrations():array{$components=['com_decaroforms'=>'Forms','com_decarodocuments'=>'Documents','com_decarocourses'=>'Courses','com_decarocompetitions'=>'Competitions','com_decaropayments'=>'Payments','com_decarocertificates'=>'Certificates'];$result=[];foreach($components as $option=>$name)$result[]=['option'=>$option,'name'=>$name,'enabled'=>ComponentHelper::isEnabled($option)];return $result;}
    public static function diagnostics():array{$db=Factory::getContainer()->get(DatabaseInterface::class);$tables=array_flip($db->getTableList());$result=[];foreach(EntityRegistry::expectedTables() as $table){$real=$db->replacePrefix($table);$result[]=['name'=>$table,'ok'=>isset($tables[$real])];}return $result;}
    public static function environment():array{try{$db=Factory::getContainer()->get(DatabaseInterface::class);$database=$db->getServerType().' '.$db->getVersion();}catch(Throwable){$database='-';}return ['joomla'=>JVERSION,'php'=>PHP_VERSION,'database'=>$database];}
}
