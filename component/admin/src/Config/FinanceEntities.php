<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Config;
defined('_JEXEC') or die;
final class FinanceEntities
{
    public static function definitions(): array
    {
        return [
            'dues'=>['table'=>'#__decaromembership_dues','label'=>'COM_DECAROMEMBERSHIP_DUES','singular'=>'COM_DECAROMEMBERSHIP_DUE','title_field'=>'association_year','search'=>['association_year','status','reference'],'list'=>['member_id','association_year','amount','paid_amount','status','due_date','reference'],'fields'=>[
                'member_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_MEMBER','type'=>'relation','relation'=>'members','required'=>true],
                'category_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CATEGORY','type'=>'relation','relation'=>'categories'],
                'association_year'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_ASSOCIATION_YEAR','type'=>'text','required'=>true],
                'amount'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_AMOUNT','type'=>'money'],
                'paid_amount'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PAID_AMOUNT','type'=>'money'],
                'status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_STATUS','type'=>'select','options'=>['unpaid'=>'COM_DECAROMEMBERSHIP_PAYMENT_UNPAID','partial'=>'COM_DECAROMEMBERSHIP_PAYMENT_PARTIAL','paid'=>'COM_DECAROMEMBERSHIP_PAYMENT_PAID','waived'=>'COM_DECAROMEMBERSHIP_PAYMENT_WAIVED']],
                'due_date'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_DUE_DATE','type'=>'date'],
                'reference'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_REFERENCE','type'=>'text'],
                'notes'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_NOTES','type'=>'textarea'],
                'published'=>['label'=>'JSTATUS','type'=>'published'],
            ]],
            'payments'=>['table'=>'#__decaromembership_payments','label'=>'COM_DECAROMEMBERSHIP_PAYMENTS','singular'=>'COM_DECAROMEMBERSHIP_PAYMENT','title_field'=>'reference','search'=>['reference','method','status'],'list'=>['reference','member_id','due_id','amount','method','status','paid_at','receipt_number'],'fields'=>[
                'member_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_MEMBER','type'=>'relation','relation'=>'members','required'=>true],
                'due_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_DUE','type'=>'relation','relation'=>'dues'],
                'amount'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_AMOUNT','type'=>'money','required'=>true],
                'method'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PAYMENT_METHOD','type'=>'select','options'=>['cash'=>'COM_DECAROMEMBERSHIP_METHOD_CASH','bank_transfer'=>'COM_DECAROMEMBERSHIP_METHOD_BANK_TRANSFER','card'=>'COM_DECAROMEMBERSHIP_METHOD_CARD','online'=>'COM_DECAROMEMBERSHIP_METHOD_ONLINE','other'=>'COM_DECAROMEMBERSHIP_METHOD_OTHER']],
                'status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_STATUS','type'=>'select','options'=>['pending'=>'COM_DECAROMEMBERSHIP_STATUS_PENDING','paid'=>'COM_DECAROMEMBERSHIP_PAYMENT_PAID','refunded'=>'COM_DECAROMEMBERSHIP_PAYMENT_REFUNDED','void'=>'COM_DECAROMEMBERSHIP_PAYMENT_VOID']],
                'paid_at'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PAID_AT','type'=>'date'],
                'reference'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_REFERENCE','type'=>'text','unique'=>true],
                'receipt_number'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_RECEIPT_NUMBER','type'=>'text','unique'=>true],
                'notes'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_NOTES','type'=>'textarea'],
                'published'=>['label'=>'JSTATUS','type'=>'published'],
            ]],
        ];
    }
}
