<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Config;
defined('_JEXEC') or die;
final class MemberCoreEntities
{
    public static function definitions(): array
    {
        return [
            'members'=>['table'=>'#__decaromembership_members','label'=>'COM_DECAROMEMBERSHIP_MEMBERS','singular'=>'COM_DECAROMEMBERSHIP_MEMBER','title_field'=>'last_name','search'=>['first_name','last_name','tax_code','member_number','card_number','email'],'list'=>['member_number','last_name','first_name','category_id','location_id','status','card_number','email'],'fields'=>[
                'first_name'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_FIRST_NAME','type'=>'text','required'=>true],
                'last_name'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_LAST_NAME','type'=>'text','required'=>true],
                'birth_date'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_BIRTH_DATE','type'=>'date'],
                'birth_place'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_BIRTH_PLACE','type'=>'text'],
                'tax_code'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_TAX_CODE','type'=>'text','unique'=>true],
                'address'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_ADDRESS','type'=>'text'],
                'city'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CITY','type'=>'text'],
                'province'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PROVINCE','type'=>'text'],
                'postal_code'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_POSTAL_CODE','type'=>'text'],
                'country'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_COUNTRY','type'=>'text'],
                'email'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_EMAIL','type'=>'email'],
                'phone'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PHONE','type'=>'text'],
                'alternative_contacts'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_ALT_CONTACTS','type'=>'textarea'],
                'photo'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PHOTO','type'=>'text'],
                'notes'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_NOTES','type'=>'textarea'],
                'member_number'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_MEMBER_NUMBER','type'=>'text','unique'=>true],
                'card_number'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CARD_NUMBER','type'=>'text','unique'=>true],
                'first_registration_date'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_FIRST_REGISTRATION','type'=>'date'],
                'status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_STATUS','type'=>'select','options'=>['active'=>'COM_DECAROMEMBERSHIP_STATUS_ACTIVE','inactive'=>'COM_DECAROMEMBERSHIP_STATUS_INACTIVE','suspended'=>'COM_DECAROMEMBERSHIP_STATUS_SUSPENDED','pending'=>'COM_DECAROMEMBERSHIP_STATUS_PENDING']],
                'location_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_LOCATION','type'=>'relation','relation'=>'locations'],
                'category_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CATEGORY','type'=>'relation','relation'=>'categories'],
                'user_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_JOOMLA_USER','type'=>'number'],
                'published'=>['label'=>'JSTATUS','type'=>'published'],
            ]],
            'categories'=>['table'=>'#__decaromembership_categories','label'=>'COM_DECAROMEMBERSHIP_CATEGORIES','singular'=>'COM_DECAROMEMBERSHIP_CATEGORY','title_field'=>'name','search'=>['name','description'],'list'=>['name','fee','duration_months','language','published'],'fields'=>[
                'name'=>['label'=>'JGLOBAL_TITLE','type'=>'text','required'=>true,'unique'=>true],
                'description'=>['label'=>'JGLOBAL_DESCRIPTION','type'=>'textarea'],
                'fee'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_FEE','type'=>'money'],
                'duration_months'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_DURATION_MONTHS','type'=>'number'],
                'required_documents'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_REQUIRED_DOCUMENTS','type'=>'textarea'],
                'rules'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_RULES','type'=>'textarea'],
                'benefits'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_BENEFITS','type'=>'textarea'],
                'language'=>['label'=>'JFIELD_LANGUAGE_LABEL','type'=>'text','default'=>'*'],
                'ordering'=>['label'=>'JFIELD_ORDERING_LABEL','type'=>'number'],
                'published'=>['label'=>'JSTATUS','type'=>'published'],
            ]],
        ];
    }
}
