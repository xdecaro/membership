<?php
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
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
$organizationTypeLabel=static function(string $type): string {
    $type=trim($type);
    if($type==='') return '';

    $key=sprintf('COM_%s_%s','DECAROMEMBERSHIP','ORGANIZATION_TYPE_'.strtoupper(preg_replace('/[^a-z0-9]+/i','_',$type)));
    $label=Text::_($key);

    return $label===$key ? $type : $label;
};
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

<?php if($isMember):
    $primaryFields=['category_id','status','member_number','first_registration_date'];
    $organizationUuid=strtolower(trim((string)($this->item->organization_uuid??'')));
?>
  <section class="dm-card dm-member-primary">
    <div class="dm-section-head">
      <div>
        <h2><?= Text::_('COM_DECAROMEMBERSHIP_MEMBER_ESSENTIALS') ?></h2>
        <p class="dm-muted mb-0"><?= Text::_('COM_DECAROMEMBERSHIP_MEMBER_ESSENTIALS_DESC') ?></p>
      </div>
    </div>
    <div class="dm-form-grid">
      <?php foreach($primaryFields as $name):
          $field=$this->config['fields'][$name]??null;
          if(!$field) continue;
          $value=$this->item->$name??($field['default']??'');

          if($name==='member_number' && $this->memberNumberAutomatic): ?>
            <div class="dm-field">
              <label for="membership_member_number_preview"><?= Text::_($field['label']) ?></label>
              <input
                type="text"
                id="membership_member_number_preview"
                value="<?= $esc($value) ?>"
                placeholder="<?= $esc(Text::_('COM_DECAROMEMBERSHIP_MEMBER_NUMBER_AUTOMATIC_PLACEHOLDER')) ?>"
                readonly
              >
              <small class="dm-muted">
                <?= Text::sprintf(
                    'COM_DECAROMEMBERSHIP_MEMBER_NUMBER_AUTOMATIC_HELP',
                    $esc($this->memberNumberPrefix !== '' ? $this->memberNumberPrefix : '—'),
                    (int)$this->memberNumberPadding
                ) ?>
              </small>
            </div>
          <?php elseif(($field['type']??'')==='relation'): ?>
            <div class="dm-field">
              <label for="jform_<?= $esc($name) ?>"><?= Text::_($field['label']) ?><?= ($field['required']??false)?' *':'' ?></label>
              <?php if($name==='category_id' && empty($this->relations[$name]??[])): ?>
                <div class="alert alert-warning mb-2" role="status">
                  <?= Text::_('COM_DECAROMEMBERSHIP_MEMBER_CATEGORY_MISSING') ?>
                </div>
                <a class="btn btn-sm btn-outline-primary mb-2" href="<?= Route::_('index.php?option=com_decaromembership&view=records&entity=categories') ?>">
                  <?= Text::_('COM_DECAROMEMBERSHIP_MEMBER_CATEGORY_MANAGE') ?>
                </a>
              <?php endif; ?>
              <select id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]"<?= ($field['required']??false)?' required':'' ?>>
                <option value="">-</option>
                <?php foreach($this->relations[$name]??[] as $opt): ?>
                  <option value="<?= (int)$opt->id ?>"<?= (int)$value===(int)$opt->id?' selected':'' ?>><?= $esc($opt->title) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php elseif($name==='first_registration_date'): ?>
            <div class="dm-field">
              <label for="jform_first_registration_date"><?= Text::_($field['label']) ?></label>
              <input type="date" id="jform_first_registration_date" name="jform[first_registration_date]" value="<?= $esc($value) ?>">
              <small class="dm-muted dm-field-help"><?= Text::_('COM_DECAROMEMBERSHIP_FIRST_REGISTRATION_HELP') ?></small>
            </div>
          <?php else:
              echo $renderField($name,$field,$value);
          endif;
      endforeach; ?>

      <?php if($this->organizationsAvailable): ?>
        <div class="dm-field dm-field-wide dm-organization-picker" data-membership-organization-picker>
          <label for="jform_organization_uuid"><?= Text::_('COM_DECAROMEMBERSHIP_FIELD_ORGANIZATION') ?></label>
          <label class="visually-hidden" for="membership_organization_search"><?= Text::_('COM_DECAROMEMBERSHIP_ORGANIZATION_SEARCH') ?></label>
          <input
            type="search"
            id="membership_organization_search"
            data-membership-organization-search
            autocomplete="off"
            placeholder="<?= $esc(Text::_('COM_DECAROMEMBERSHIP_ORGANIZATION_SEARCH_PLACEHOLDER')) ?>"
            aria-controls="jform_organization_uuid"
          >
          <select id="jform_organization_uuid" name="jform[organization_uuid]" data-membership-organization-select>
            <option value="" data-search=""><?= Text::_('COM_DECAROMEMBERSHIP_ORGANIZATION_NONE') ?></option>
            <?php foreach($this->organizationOptions as $organization):
                $uuid=strtolower(trim((string)($organization['uuid']??'')));
                if($uuid==='') continue;
                $name=trim((string)($organization['name']??''));
                $path=trim((string)($organization['path']??$name));
                $type=trim((string)($organization['type']??''));
                $typeLabel=$organizationTypeLabel($type);
                $depth=max(0,min(12,(int)($organization['depth']??0)));
                $prefix=$depth>0 ? str_repeat(' ',$depth).'↳ ' : '';
                $label=$prefix.$name;
                if($typeLabel!=='') $label.=' · '.$typeLabel;
                $searchText=trim($path.' '.$name.' '.$typeLabel);
            ?>
              <option
                value="<?= $esc($uuid) ?>"
                data-search="<?= $esc($searchText) ?>"
                data-path="<?= $esc($path) ?>"
                <?= $organizationUuid===$uuid?' selected':'' ?>
              ><?= $esc($label) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="dm-muted" data-membership-organization-path></small>
          <small class="dm-muted" data-membership-organization-empty hidden><?= Text::_('COM_DECAROMEMBERSHIP_ORGANIZATION_SEARCH_EMPTY') ?></small>
          <small class="dm-muted"><?= Text::_('COM_DECAROMEMBERSHIP_ORGANIZATION_OPTIONAL_HELP') ?></small>
        </div>
      <?php elseif($organizationUuid!==''): ?>
        <input type="hidden" name="jform[organization_uuid]" value="<?= $esc($organizationUuid) ?>">
        <div class="dm-field dm-field-wide">
          <div class="alert alert-warning mb-0"><?= Text::_('COM_DECAROMEMBERSHIP_ORGANIZATION_LINK_PRESERVED') ?></div>
        </div>
      <?php else: ?>
        <div class="dm-field dm-field-wide">
          <p class="dm-muted mb-0"><?= Text::_('COM_DECAROMEMBERSHIP_ORGANIZATION_NOT_REQUIRED') ?></p>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <details class="dm-card dm-member-advanced">
    <summary><?= Text::_('COM_DECAROMEMBERSHIP_MEMBER_ADVANCED') ?></summary>
    <p class="dm-muted"><?= Text::_('COM_DECAROMEMBERSHIP_MEMBER_ADVANCED_DESC') ?></p>
    <div class="dm-form-grid">
    <?php foreach($this->config['fields'] as $name=>$field):
        if(in_array($name,$peopleOwnedFields,true) || in_array($name,$primaryFields,true) || $name==='organization_uuid') continue;
        $value=$this->item->$name??($field['default']??'');
        if(($field['type']??'')==='relation'): ?>
          <div class="dm-field">
            <label for="jform_<?= $esc($name) ?>"><?= Text::_($field['label']) ?><?= ($field['required']??false)?' *':'' ?></label>
            <select id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]"<?= ($field['required']??false)?' required':'' ?>>
              <option value="">-</option>
              <?php foreach($this->relations[$name]??[] as $opt): ?>
                <option value="<?= (int)$opt->id ?>"<?= (int)$value===(int)$opt->id?' selected':'' ?>><?= $esc($opt->title) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if($name==='location_id'): ?><small class="dm-muted"><?= Text::_('COM_DECAROMEMBERSHIP_LOCATION_LEGACY_HELP') ?></small><?php endif; ?>
          </div>
        <?php else: echo $renderField($name,$field,$value); endif;
    endforeach; ?>
    </div>
  </details>
<?php else: ?>
  <div class="dm-card dm-form-grid">
  <?php foreach($this->config['fields'] as $name=>$field):
      $value=$this->item->$name??($field['default']??'');
      if(($field['type']??'')==='relation'): ?>
        <div class="dm-field"><label for="jform_<?= $esc($name) ?>"><?= Text::_($field['label']) ?><?= ($field['required']??false)?' *':'' ?></label><select id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]"<?= ($field['required']??false)?' required':'' ?>><option value="">-</option><?php foreach($this->relations[$name]??[] as $opt): ?><option value="<?= (int)$opt->id ?>"<?= (int)$value===(int)$opt->id?' selected':'' ?>><?= $esc($opt->title) ?></option><?php endforeach; ?></select></div>
      <?php else: echo $renderField($name,$field,$value); endif; ?>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<input type="hidden" name="id" value="<?= (int)($this->item->id??0) ?>"><input type="hidden" name="entity" value="<?= $esc($this->entity) ?>"><input type="hidden" name="task" value=""><?= HTMLHelper::_('form.token') ?></form>