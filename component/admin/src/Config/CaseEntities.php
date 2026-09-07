<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Config;
defined('_JEXEC') or die;
final class CaseEntities
{
    public static function definitions(): array
    {
        return [
            'case_statuses'=>['table'=>'#__decaromembership_case_statuses','label'=>'COM_DECAROMEMBERSHIP_CASE_STATUSES','singular'=>'COM_DECAROMEMBERSHIP_CASE_STATUS','title_field'=>'name','search'=>['name','code'],'list'=>['name','code','ordering','language','published'],'fields'=>[
                'name'=>['label'=>'JGLOBAL_TITLE','type'=>'text','required'=>true],
                'code'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CODE','type'=>'text','required'=>true,'unique'=>true],
                'ordering'=>['label'=>'JFIELD_ORDERING_LABEL','type'=>'number'],
                'language'=>['label'=>'JFIELD_LANGUAGE_LABEL','type'=>'text','default'=>'*'],
                'published'=>['label'=>'JSTATUS','type'=>'published'],
            ]],
            'cases'=>['table'=>'#__decaromembership_cases','label'=>'COM_DECAROMEMBERSHIP_CASES','singular'=>'COM_DECAROMEMBERSHIP_CASE','title_field'=>'reference','search'=>['reference','type','notes'],'list'=>['reference','member_id','type','status_id','assigned_to','received_at','completed_at'],'fields'=>[
                'reference'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_REFERENCE','type'=>'text','required'=>true,'unique'=>true],
                'member_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_MEMBER','type'=>'relation','relation'=>'members','required'=>true],
                'type'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CASE_TYPE','type'=>'select','options'=>['registration'=>'COM_DECAROMEMBERSHIP_CASE_TYPE_REGISTRATION','renewal'=>'COM_DECAROMEMBERSHIP_CASE_TYPE_RENEWAL','data_change'=>'COM_DECAROMEMBERSHIP_CASE_TYPE_DATA_CHANGE','transfer'=>'COM_DECAROMEMBERSHIP_CASE_TYPE_TRANSFER','duplicate_card'=>'COM_DECAROMEMBERSHIP_CASE_TYPE_DUPLICATE_CARD','other'=>'COM_DECAROMEMBERSHIP_CASE_TYPE_OTHER']],
                'status_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_STATUS','type'=>'relation','relation'=>'case_statuses'],
                'assigned_to'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_ASSIGNED_TO','type'=>'number'],
                'source_component'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_SOURCE_COMPONENT','type'=>'text'],
                'source_entity_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_SOURCE_ID','type'=>'number'],
                'received_at'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_RECEIVED_AT','type'=>'date'],
                'completed_at'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_COMPLETED_AT','type'=>'date'],
                'notes'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_NOTES','type'=>'textarea'],
                'published'=>['label'=>'JSTATUS','type'=>'published'],
            ]],
            'renewals'=>['table'=>'#__decaromembership_renewals','label'=>'COM_DECAROMEMBERSHIP_RENEWALS','singular'=>'COM_DECAROMEMBERSHIP_RENEWAL','title_field'=>'association_year','search'=>['association_year','status'],'list'=>['member_id','association_year','status','renewal_date','expiry_date','amount','payment_status'],'fields'=>[
                'member_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_MEMBER','type'=>'relation','relation'=>'members','required'=>true],
                'association_year'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_ASSOCIATION_YEAR','type'=>'text','required'=>true],
                'renewal_date'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_RENEWAL_DATE','type'=>'date'],
                'expiry_date'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_EXPIRY_DATE','type'=>'date'],
                'status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_STATUS','type'=>'select','options'=>['due'=>'COM_DECAROMEMBERSHIP_RENEWAL_DUE','in_progress'=>'COM_DECAROMEMBERSHIP_RENEWAL_IN_PROGRESS','completed'=>'COM_DECAROMEMBERSHIP_RENEWAL_COMPLETED','late'=>'COM_DECAROMEMBERSHIP_RENEWAL_LATE','cancelled'=>'COM_DECAROMEMBERSHIP_RENEWAL_CANCELLED']],
                'amount'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_AMOUNT','type'=>'money'],
                'payment_status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PAYMENT_STATUS','type'=>'select','options'=>['unpaid'=>'COM_DECAROMEMBERSHIP_PAYMENT_UNPAID','partial'=>'COM_DECAROMEMBERSHIP_PAYMENT_PARTIAL','paid'=>'COM_DECAROMEMBERSHIP_PAYMENT_PAID','refunded'=>'COM_DECAROMEMBERSHIP_PAYMENT_REFUNDED']],
                'notes'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_NOTES','type'=>'textarea'],
                'published'=>['label'=>'JSTATUS','type'=>'published'],
            ]],
            'cards'=>['table'=>'#__decaromembership_cards','label'=>'COM_DECAROMEMBERSHIP_CARDS','singular'=>'COM_DECAROMEMBERSHIP_CARD','title_field'=>'card_number','search'=>['card_number','type','status'],'list'=>['card_number','member_id','type','status','issued_at','activated_at','expires_at','annual_mark'],'fields'=>[
                'member_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_MEMBER','type'=>'relation','relation'=>'members','required'=>true],
                'card_number'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CARD_NUMBER','type'=>'text','required'=>true,'unique'=>true],
                'type'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CARD_TYPE','type'=>'select','options'=>['physical'=>'COM_DECAROMEMBERSHIP_CARD_PHYSICAL','electronic'=>'COM_DECAROMEMBERSHIP_CARD_ELECTRONIC']],
                'status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_STATUS','type'=>'select','options'=>['pending'=>'COM_DECAROMEMBERSHIP_STATUS_PENDING','active'=>'COM_DECAROMEMBERSHIP_STATUS_ACTIVE','expired'=>'COM_DECAROMEMBERSHIP_STATUS_EXPIRED','lost'=>'COM_DECAROMEMBERSHIP_STATUS_LOST','revoked'=>'COM_DECAROMEMBERSHIP_STATUS_REVOKED','replaced'=>'COM_DECAROMEMBERSHIP_STATUS_REPLACED']],
                'issued_at'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_ISSUED_AT','type'=>'date'],
                'activated_at'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_ACTIVATED_AT','type'=>'date'],
                'expires_at'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_EXPIRY_DATE','type'=>'date'],
                'annual_mark'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_ANNUAL_MARK','type'=>'text'],
                'qr_token'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_QR_TOKEN','type'=>'text','unique'=>true],
                'notes'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_NOTES','type'=>'textarea'],
                'published'=>['label'=>'JSTATUS','type'=>'published'],
            ]],
        ];
    }
}
