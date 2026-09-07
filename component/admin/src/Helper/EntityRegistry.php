<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Helper;
defined('_JEXEC') or die;
use Xdecaro\Component\Decaromembership\Administrator\Config\CaseEntities;
use Xdecaro\Component\Decaromembership\Administrator\Config\FinanceEntities;
use Xdecaro\Component\Decaromembership\Administrator\Config\MemberCoreEntities;
use Xdecaro\Component\Decaromembership\Administrator\Config\OperationsEntities;
use Xdecaro\Component\Decaromembership\Administrator\Config\OrganizationEntities;
final class EntityRegistry
{
    private static ?array $entities=null;
    public static function all():array{if(self::$entities===null)self::$entities=array_replace(MemberCoreEntities::definitions(),OrganizationEntities::definitions(),CaseEntities::definitions(),OperationsEntities::definitions(),FinanceEntities::definitions());return self::$entities;}
    public static function has(string $entity):bool{return isset(self::all()[$entity]);}
    public static function get(string $entity):array{$all=self::all();return $all[$entity]??$all['members'];}
    public static function expectedTables():array{return ['#__decaromembership_members','#__decaromembership_categories','#__decaromembership_case_statuses','#__decaromembership_cases','#__decaromembership_case_status_history','#__decaromembership_renewals','#__decaromembership_cards','#__decaromembership_dues','#__decaromembership_payments','#__decaromembership_transfers','#__decaromembership_locations','#__decaromembership_relations','#__decaromembership_documents','#__decaromembership_checklist_templates','#__decaromembership_checklist_items','#__decaromembership_notifications','#__decaromembership_entity_links','#__decaromembership_audit_log'];}
}
