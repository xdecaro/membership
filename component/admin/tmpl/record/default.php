<?php
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
$wa=$this->getDocument()->getWebAssetManager();
if(!$wa->assetExists('style','com_decaromembership.admin')){
    $wa->registerAndUseStyle('com_decaromembership.admin','com_decaromembership/css/admin.css',['version'=>'auto']);
}else{
    $wa->useStyle('com_decaromembership.admin');
}
if(!$wa->assetExists('script','com_decaromembership.admin')){
    $wa->registerAndUseScript('com_decaromembership.admin','com_decaromembership/js/admin.js',['version'=>'auto'],['defer'=>true],['core']);
}else{
    $wa->useScript('com_decaromembership.admin');
}
$esc=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$peopleOwnedFields=['person_uuid','first_name','last_name','birth_date','birth_place','tax_code','address','city','province','postal_code','country','email','phone','user_id'];
$renderField=function(string $name,array $field,mixed $value) use($esc){
    $required=($field['required']??false)?' required':'';
    ob_start(); ?>
    <div class="dm-field <?= ($field['type']??'')==='textarea'?'dm-field-wide':'' ?>"><label for="jform_<?= $esc($name) ?>"><?= Text::_($field['label']) ?><?= ($field['required']??false)?' *':'' ?></label>
    <?php switch($field['type']??'text'):
        case 'textarea': ?><textarea id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]" rows="4"<?= $required ?>><?= $esc($value) ?></textarea><?php break;
        case 'select': ?><select id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]"<?= $required ?>><option value="">-</option><?php foreach($field['options'] as $k=>$label): ?><option value="<?= $esc($k) ?>"<?= (string)$value===(string)$k?' selected':'' ?>><?= Text::_($label) ?></option><?php endforeach; ?></select><?php break;
        case 'boolean': case 'published': ?><select id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]"><option value="1"<?= (int)$value===1?' selected':'' ?>><?= Text::_('JYES') ?></option><option value="0"<?= (int)$value===0?' selected':'' ?>><?= Text::_('JNO') ?></option></select><?php break;
        case 'date': ?><input type="date" id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]" value="<?= $esc($value) ?>"<?= $required ?>><?php break;
        case 'number': case 'money': ?><input type="number" step="<?= ($field['type']??'')==='money'?'0.01':'1' ?>" id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]" value="<?= $esc($value) ?>"<?= $required ?>><?php break;
        case 'email': ?><input type="email" id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]" value="<?= $esc($value) ?>"<?= $required ?>><?php break;
        default: ?><input type="text" id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]" value="<?= $esc($value) ?>"<?= $required ?>><?php endswitch; ?></div>
    <?php return (string)ob_get_clean();
};
$linkedUuid=strtolower(trim((string)($this->item->person_uuid??'')));
$isMember=$this->entity==='members';
?>
<form action="<?= Route::_('index.php?option=com_decaromembership&entity='.$this->entity.'&id='.(int)($this->item->id??0)) ?>" method="post" name="adminForm" id="adminForm" class="dm-page">
<?php if($isMember): ?>
  <section class="dm-card dm-person-card">
    <div class="dm-section-head"><h2><?= Text::_('COM_DECAROMEMBERSHIP_PEOPLE_IDENTITY') ?></h2><?php if($linkedUuid!==''): ?><span class="dm-badge is-ok"><?= Text::_('COM_DECAROMEMBERSHIP_PEOPLE_LINKED') ?></span><?php endif; ?></div>
    <?php if($linkedUuid!=='' && $this->person): ?>
      <div data-membership-person-summary class="dm-person-summary">
        <strong><?= $esc($this->person['display_name']??trim(($this->person['first_name']??'').' '.($this->person['last_name']??''))) ?></strong>
        <div class="dm-person-meta"><?php if(!empty($this->person['email'])): ?><span><?= $esc($this->person['email']) ?></span><?php endif; ?><?php if(!empty($this->person['phone'])): ?><span><?= $esc($this->person['phone']) ?></span><?php endif; ?></div>
        <?php if($this->personSensitive): ?><dl class="dm-person-details"><?php if(!empty($this->person['birth_date'])): ?><dt><?= Text::_('COM_DECAROMEMBERSHIP_FIELD_BIRTH_DATE') ?></dt><dd><?= $esc($this->person['birth_date']) ?></dd><?php endif; ?><?php if(!empty($this->person['tax_identifier'])): ?><dt><?= Text::_('COM_DECAROMEMBERSHIP_FIELD_TAX_CODE') ?></dt><dd><?= $esc($this->person['tax_identifier']) ?></dd><?php endif; ?><?php if(!empty($this->person['address_line'])): ?><dt><?= Text::_('COM_DECAROMEMBERSHIP_FIELD_ADDRESS') ?></dt><dd><?= $esc(trim(($this->person['address_line']??'').' '.($this->person['address_number']??''))) ?></dd><?php endif; ?></dl><?php endif; ?>
        <?php if(!empty($this->person['id'])): ?><a class="btn btn-sm btn-outline-secondary" href="<?= Route::_('index.php?option=com_xdecaropeople&task=person.edit&id='.(int)$this->person['id']) ?>"><?= Text::_('COM_DECAROMEMBERSHIP_PEOPLE_OPEN') ?></a><?php endif; ?>
      </div>
      <?php if($this->canRelinkPerson): ?>
        <button type="button" class="btn btn-outline-warning mt-3" data-membership-person-relink aria-expanded="false"><?= Text::_('COM_DECAROMEMBERSHIP_PEOPLE_RELINK') ?></button>
        <div class="dm-people-relink mt-3" data-membership-relink-panel hidden>
          <div data-membership-people-picker data-relink="1">
            <label for="membership_relink_search"><?= Text::_('COM_DECAROMEMBERSHIP_PEOPLE_SEARCH') ?></label>
            <input type="search" id="membership_relink_search" data-membership-people-search autocomplete="off" placeholder="<?= $esc(Text::_('COM_DECAROMEMBERSHIP_PEOPLE_SEARCH_PLACEHOLDER')) ?>">
            <input type="hidden" id="membership_relink_uuid" data-membership-person-target value="">
            <div class="dm-people-results" data-membership-people-results></div>
            <div class="dm-person-summary" data-membership-person-summary hidden></div>
            <button type="button" class="btn btn-warning mt-2" data-membership-relink-submit data-member-id="<?= (int)($this->item->id??0) ?>" data-confirm="<?= $esc(Text::_('COM_DECAROMEMBERSHIP_PEOPLE_RELINK_CONFIRM')) ?>" disabled><?= Text::_('COM_DECAROMEMBERSHIP_PEOPLE_RELINK_CONFIRM_BUTTON') ?></button>
          </div>
        </div>
      <?php endif; ?>
    <?php elseif($linkedUuid!==''): ?>
      <div data-membership-person-summary class="alert alert-warning mb-0"><?= Text::_('COM_DECAROMEMBERSHIP_PEOPLE_UNAVAILABLE') ?></div>
    <?php else: ?>
      <?php if((int)($this->item->id??0)>0): ?>
        <div class="dm-legacy-identity"><p class="dm-muted"><?= Text::_('COM_DECAROMEMBERSHIP_PEOPLE_LEGACY_NOTICE') ?></p><dl><?php foreach(['first_name','last_name','birth_date','email','phone','address','city'] as $legacyField): $legacyValue=$this->item->$legacyField??''; if($legacyValue===''||$legacyValue===null) continue; ?><dt><?= Text::_($this->config['fields'][$legacyField]['label']??$legacyField) ?></dt><dd><?= $esc($legacyValue) ?></dd><?php endforeach; ?></dl></div>
      <?php endif; ?>
      <div data-membership-people-picker>
        <label for="membership_people_search"><?= Text::_('COM_DECAROMEMBERSHIP_PEOPLE_SEARCH') ?> *</label>
        <input type="search" id="membership_people_search" data-membership-people-search autocomplete="off" placeholder="<?= $esc(Text::_('COM_DECAROMEMBERSHIP_PEOPLE_SEARCH_PLACEHOLDER')) ?>">
        <input type="hidden" id="jform_person_uuid" name="jform[person_uuid]" data-membership-person-target value="">
        <div class="dm-people-results" data-membership-people-results></div>
        <div class="dm-person-summary" data-membership-person-summary hidden></div>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>
<div class="dm-card dm-form-grid">
<?php foreach($this->config['fields'] as $name=>$field):
    if($isMember && in_array($name,$peopleOwnedFields,true)) continue;
    $value=$this->item->$name??($field['default']??'');
    if(($field['type']??'')==='relation'): ?>
      <div class="dm-field"><label for="jform_<?= $esc($name) ?>"><?= Text::_($field['label']) ?><?= ($field['required']??false)?' *':'' ?></label><select id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]"<?= ($field['required']??false)?' required':'' ?>><option value="">-</option><?php foreach($this->relations[$name]??[] as $opt): ?><option value="<?= (int)$opt->id ?>"<?= (int)$value===(int)$opt->id?' selected':'' ?>><?= $esc($opt->title) ?></option><?php endforeach; ?></select></div>
    <?php else: echo $renderField($name,$field,$value); endif; ?>
<?php endforeach; ?>
</div>
<input type="hidden" name="id" value="<?= (int)($this->item->id??0) ?>"><input type="hidden" name="entity" value="<?= $esc($this->entity) ?>"><input type="hidden" name="task" value=""><?= HTMLHelper::_('form.token') ?></form>
