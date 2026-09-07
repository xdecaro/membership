<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Config;
defined('_JEXEC') or die;
final class OrganizationEntities
{
    public static function definitions(): array
    {
        return [
            'locations'=>['table'=>'#__decaromembership_locations','label'=>'COM_DECAROMEMBERSHIP_LOCATIONS','singular'=>'COM_DECAROMEMBERSHIP_LOCATION','title_field'=>'name','search'=>['name','code','number','email'],'list'=>['name','code','number','city','parent_id','language','published'],'fields'=>[
                'name'=>['label'=>'JGLOBAL_TITLE','type'=>'text','required'=>true],
                'code'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CODE','type'=>'text','unique'=>true],
                'number'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_NUMBER','type'=>'text','unique'=>true],
                'address'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_ADDRESS','type'=>'text'],
                'city'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CITY','type'=>'text'],
                'province'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PROVINCE','type'=>'text'],
                'postal_code'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_POSTAL_CODE','type'=>'text'],
                'country'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_COUNTRY','type'=>'text'],
                'email'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_EMAIL','type'=>'email'],
                'phone'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PHONE','type'=>'text'],
                'manager'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_MANAGER','type'=>'text'],
                'parent_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PARENT_LOCATION','type'=>'relation','relation'=>'locations'],
                'language'=>['label'=>'JFIELD_LANGUAGE_LABEL','type'=>'text','default'=>'*'],
                'published'=>['label'=>'JSTATUS','type'=>'published'],
            ]],
            'relations'=>['table'=>'#__decaromembership_relations','label'=>'COM_DECAROMEMBERSHIP_RELATIONS','singular'=>'COM_DECAROMEMBERSHIP_RELATION','title_field'=>'relation_type','search'=>['relation_type'],'list'=>['member_id','related_member_id','relation_type','valid_from','valid_to','published'],'fields'=>[
                'member_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_MEMBER','type'=>'relation','relation'=>'members','required'=>true],
                'related_member_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_RELATED_MEMBER','type'=>'relation','relation'=>'members','required'=>true],
                'relation_type'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_RELATION_TYPE','type'=>'select','options'=>['parent'=>'COM_DECAROMEMBERSHIP_RELATION_PARENT','child'=>'COM_DECAROMEMBERSHIP_RELATION_CHILD','guardian'=>'COM_DECAROMEMBERSHIP_RELATION_GUARDIAN','spouse'=>'COM_DECAROMEMBERSHIP_RELATION_SPOUSE','other'=>'COM_DECAROMEMBERSHIP_RELATION_OTHER']],
                'valid_from'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_VALID_FROM','type'=>'date'],
                'valid_to'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_VALID_TO','type'=>'date'],
                'notes'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_NOTES','type'=>'textarea'],
                'published'=>['label'=>'JSTATUS','type'=>'published'],
            ]],
        ];
    }
}
