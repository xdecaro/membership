<?php
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
$esc=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$seasonDisplayLabel=static function(array $season): string {
    $tournament=trim((string)($season['tournament_name']??''));
    $year=trim((string)($season['season_year']??''));
    $hostCity=trim((string)($season['host_city']??''));
    $hostCountry=trim((string)($season['host_country_code']??''));

    $primary=$tournament;
    if($year!=='' && ($primary==='' || !preg_match('/(?:^|\D)'.preg_quote($year,'/').'(?:\D|$)/',$primary))){
        $primary=trim($primary.' '.$year);
    }

    $host=trim(implode(', ',array_filter([$hostCity,$hostCountry],static fn($value)=>$value!=='')));
    $label=$primary;
    if($host!=='') $label=trim($label.($label!==''?' — ':'').$host);

    if($label==='') $label=trim((string)($season['label']??($season['name']??'')));
    return $label;
};
$peopleOwnedFields=['person_uuid','first_name','last_name','birth_date','birth_place','tax_code','address','city','province','postal_code','country','email','phone','user_id'];
$renderField=function(string $name,array $field,mixed $value) use($esc,$seasonDisplayLabel){
    $required=($field['required']??false)?' required':'';
    ob_start(); ?>
    <div class="dm-field <?= ($field['type']??'')==='textarea'?'dm-field-wide':'' ?>"<?= ($field['type']??'')==='dcl_season'?' data-membership-dcl-only':'' ?>><label for="jform_<?= $esc($name) ?>"><?= Text::_($field['label']) ?><?= ($field['required']??false)?' *':'' ?></label>
    <?php switch($field['type']??'text'):
        case 'textarea': ?><textarea id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]" rows="4"<?= $required ?>><?= $esc($value) ?></textarea><?php break;
        case 'select': ?><select id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]"<?= $required ?>><option value="">-</option><?php foreach($field['options'] as $k=>$label): ?><option value="<?= $esc($k) ?>"<?= (string)$value===(string)$k?' selected':'' ?>><?= Text::_($label) ?></option><?php endforeach; ?></select><?php break;
        case 'boolean': case 'published': ?><select id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]"><option value="1"<?= (int)$value===1?' selected':'' ?>><?= Text::_('JYES') ?></option><option value="0"<?= (int)$value===0?' selected':'' ?>><?= Text::_('JNO') ?></option></select><?php break;
        case 'date': ?><input type="date" id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]" value="<?= $esc($value) ?>"<?= $required ?>><?php break;
        case 'dcl_season':
            if($this->competitionsAvailable): ?>
              <?php if($this->competitionSeasonOptions): ?>
                <select id="jform_competition_season_id" name="jform[competition_season_id]" data-membership-dcl-season>
                  <option value=""><?= Text::_('COM_DECAROMEMBERSHIP_DCL_SEASON_SELECT') ?></option>
                  <?php foreach($this->competitionSeasonOptions as $season):
                      $seasonId=(int)($season['id']??0);
                      if($seasonId<1) continue;
                      $seasonValue=trim((string)($season['season_year']??''));
                      if($seasonValue==='') $seasonValue=trim((string)($season['name']??''));
                  ?>
                    <?php
                      $rightsHolderName=trim((string)($season['rights_holder_name']??''));
                      $rightsHolderShort=trim((string)($season['rights_holder_short_name']??''));
                      $rightsHolderLabel=$rightsHolderName;
                      if($rightsHolderLabel!=='' && $rightsHolderShort!=='' && strcasecmp($rightsHolderLabel,$rightsHolderShort)!==0) $rightsHolderLabel.=' ('.$rightsHolderShort.')';
                    ?>
                    <option
                      value="<?= $seasonId ?>"
                      data-season="<?= $esc($seasonValue) ?>"
                      data-start="<?= $esc($season['start_date']??'') ?>"
                      data-end="<?= $esc($season['end_date']??'') ?>"
                      data-tournament-code="<?= $esc($season['tournament_code']??'') ?>"
                      data-issuer-uuid="<?= $esc(strtolower(trim((string)($season['rights_holder_organization_uuid']??'')))) ?>"
                      data-issuer-label="<?= $esc($rightsHolderLabel) ?>"
                      <?= $this->selectedCompetitionSeasonId===$seasonId?' selected':'' ?>
                    ><?= $esc($seasonDisplayLabel($season) ?: '#'.$seasonId) ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" id="jform_season" name="jform[season]" value="<?= $esc($value) ?>" data-membership-dcl-season-value>
                <small class="dm-muted"><?= Text::_('COM_DECAROMEMBERSHIP_DCL_SEASON_LINK_HELP') ?></small>
              <?php else: ?>
                <div class="alert alert-warning mb-0"><?= Text::_('COM_DECAROMEMBERSHIP_DCL_SEASON_NONE') ?></div>
                <input type="hidden" id="jform_season" name="jform[season]" value="<?= $esc($value) ?>">
              <?php endif; ?>
            <?php else: ?>
              <?php if($this->selectedCompetitionSeasonId>0): ?>
                <input type="hidden" name="jform[competition_season_id]" value="<?= (int)$this->selectedCompetitionSeasonId ?>">
              <?php endif; ?>
              <input type="text" id="jform_season" name="jform[season]" value="<?= $esc($value) ?>" placeholder="2026">
              <small class="dm-muted"><?= Text::_('COM_DECAROMEMBERSHIP_DCL_SEASON_MANUAL_HELP') ?></small>
            <?php endif;
            break;
        case 'number': case 'money': ?><input type="number" step="<?= ($field['type']??'')==='money'?'0.01':'1' ?>" id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]" value="<?= $esc($value) ?>"<?= $required ?>><?php break;
        case 'email': ?><input type="email" id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]" value="<?= $esc($value) ?>"<?= $required ?>><?php break;
        case 'organization':
            if($this->entity==='card_numbering_rules' && $name==='issuer_organization_uuid'):
                $selectedName=trim((string)($this->selectedOrganization['name']??''));
                $selectedShort=trim((string)($this->selectedOrganization['short_name']??''));
                $selectedLabel=$selectedName;
                if($selectedLabel!=='' && $selectedShort!=='' && strcasecmp($selectedLabel,$selectedShort)!==0) $selectedLabel.=' ('.$selectedShort.')';
                if($this->organizationsAvailable): ?>
                  <div class="dm-organization-picker" data-membership-issuer-picker>
                    <input
                      type="search"
                      id="membership_numbering_issuer_search"
                      data-membership-issuer-search
                      data-selected-label="<?= $esc($selectedLabel) ?>"
                      autocomplete="off"
                      value="<?= $esc($selectedLabel) ?>"
                      placeholder="<?= $esc(Text::_('COM_DECAROMEMBERSHIP_ORGANIZATION_SEARCH_PLACEHOLDER')) ?>"
                      <?= $required ?>
                    >
                    <input type="hidden" id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]" data-membership-issuer-target value="<?= $esc(strtolower(trim((string)$value))) ?>">
                    <div class="dm-people-results" data-membership-issuer-results></div>
                    <small class="dm-muted"><?= Text::_('COM_DECAROMEMBERSHIP_NUMBERING_ISSUER_HELP') ?></small>
                  </div>
                <?php else: ?>
                  <?php if(trim((string)$value)!==''): ?><input type="hidden" id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]" value="<?= $esc($value) ?>"><?php endif; ?>
                  <div class="alert alert-warning mb-0"><?= Text::_('COM_DECAROMEMBERSHIP_ORGANIZATIONS_UNAVAILABLE') ?></div>
                <?php endif;
            elseif($this->organizationsAvailable):
                $searchId='membership_organization_search_'.$name;
                ?>
                <div class="dm-organization-picker" data-membership-organization-picker>
                  <label class="visually-hidden" for="<?= $esc($searchId) ?>"><?= Text::_('COM_DECAROMEMBERSHIP_ORGANIZATION_SEARCH') ?></label>
                  <input
                    type="search"
                    id="<?= $esc($searchId) ?>"
                    data-membership-organization-search
                    autocomplete="off"
                    placeholder="<?= $esc(Text::_('COM_DECAROMEMBERSHIP_ORGANIZATION_SEARCH_PLACEHOLDER')) ?>"
                    aria-controls="jform_<?= $esc($name) ?>"
                  >
                  <select id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]" data-membership-organization-select<?= $required ?>>
                    <option value="" data-search=""><?= Text::_('COM_DECAROMEMBERSHIP_ORGANIZATION_NONE') ?></option>
                    <?php foreach($this->organizationOptions as $organization):
                        $uuid=strtolower(trim((string)($organization['uuid']??'')));
                        if($uuid==='') continue;
                        $orgName=trim((string)($organization['name']??''));
                        $pathText=trim((string)($organization['path']??$orgName));
                        $type=trim((string)($organization['type']??''));
                        $key=sprintf('COM_%s_%s','DECAROMEMBERSHIP','ORGANIZATION_TYPE_'.strtoupper(preg_replace('/[^a-z0-9]+/i','_',$type)));
                        $typeLabel=$type===''?'':Text::_($key);
                        if($typeLabel===$key) $typeLabel=$type;
                        $depth=max(0,min(12,(int)($organization['depth']??0)));
                        $prefix=$depth>0 ? str_repeat(' ',$depth).'↳ ' : '';
                        $label=$prefix.$orgName;
                        if($typeLabel!=='') $label.=' · '.$typeLabel;
                        $searchText=trim($pathText.' '.$orgName.' '.$typeLabel);
                    ?>
                      <option
                        value="<?= $esc($uuid) ?>"
                        data-search="<?= $esc($searchText) ?>"
                        data-path="<?= $esc($pathText) ?>"
                        <?= strtolower(trim((string)$value))===$uuid?' selected':'' ?>
                      ><?= $esc($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small class="dm-muted" data-membership-organization-path></small>
                  <small class="dm-muted" data-membership-organization-empty hidden><?= Text::_('COM_DECAROMEMBERSHIP_ORGANIZATION_SEARCH_EMPTY') ?></small>
                </div>
            <?php else: ?>
                <?php if(trim((string)$value)!==''): ?>
                  <input type="hidden" id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]" value="<?= $esc($value) ?>">
                <?php endif; ?>
                <div class="alert alert-warning mb-0"><?= Text::_('COM_DECAROMEMBERSHIP_ORGANIZATIONS_UNAVAILABLE') ?></div>
            <?php endif;
            break;
        default: ?><input type="text" id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]" value="<?= $esc($value) ?>"<?= $required ?>><?php endswitch; ?></div>
    <?php return (string)ob_get_clean();
};
$linkedUuid=strtolower(trim((string)($this->item->person_uuid??'')));
$isMember=$this->entity==='members';
$isCard=$this->entity==='cards';
$cardStatusKey=[
    'pending'=>'COM_DECAROMEMBERSHIP_CARD_STATUS_PENDING',
    'in_review'=>'COM_DECAROMEMBERSHIP_CARD_STATUS_IN_REVIEW',
    'active'=>'COM_DECAROMEMBERSHIP_CARD_STATUS_ACTIVE',
    'suspended'=>'COM_DECAROMEMBERSHIP_CARD_STATUS_SUSPENDED',
    'expired'=>'COM_DECAROMEMBERSHIP_CARD_STATUS_EXPIRED',
    'lost'=>'COM_DECAROMEMBERSHIP_CARD_STATUS_LOST',
    'revoked'=>'COM_DECAROMEMBERSHIP_CARD_STATUS_REVOKED',
    'replaced'=>'COM_DECAROMEMBERSHIP_CARD_STATUS_REPLACED',
];
$cardTypeKey=[
    'physical'=>'COM_DECAROMEMBERSHIP_CARD_PHYSICAL',
    'electronic'=>'COM_DECAROMEMBERSHIP_CARD_ELECTRONIC',
];
$organizationTypeLabel=static function(string $type): string {
    $type=trim($type);
    if($type==='') return '';

    $key=sprintf('COM_%s_%s','DECAROMEMBERSHIP','ORGANIZATION_TYPE_'.strtoupper(preg_replace('/[^a-z0-9]+/i','_',$type)));
    $label=Text::_($key);

    return $label===$key ? $type : $label;
};
?>

<form action="<?= Route::_('index.php?option=com_decaromembership&entity='.$this->entity.'&id='.(int)($this->item->id??0)) ?>" method="post" name="adminForm" id="adminForm" class="dm-page">
<?php if($isCard):
    $personUuid=strtolower(trim((string)($this->item->person_uuid??'')));
    $personName=$this->person ? trim((string)($this->person['display_name']??'')) : '';
    if($personName==='' && $this->person) $personName=trim((string)(($this->person['first_name']??'').' '.($this->person['last_name']??'')));
    $scope=(string)($this->item->scope??(((string)($this->item->program??''))==='dcl'?'competition':'association'));
    $status=(string)($this->item->status??'pending');
?>
  <section class="dm-card" data-membership-card-form>
    <div class="dm-section-head">
      <div>
        <h2><?= Text::_('COM_DECAROMEMBERSHIP_CARD_ESSENTIALS') ?></h2>
        <p class="dm-muted mb-0"><?= Text::_('COM_DECAROMEMBERSHIP_CARD_ESSENTIALS_DESC') ?></p>
      </div>
    </div>
    <div class="dm-form-grid">
      <div class="dm-field dm-field-wide" data-membership-people-picker>
        <label for="membership_card_person_search"><?= Text::_('COM_DECAROMEMBERSHIP_FIELD_HOLDER_PERSON') ?> *</label>
        <input type="search" id="membership_card_person_search" data-membership-people-search autocomplete="off" value="<?= $esc($personName) ?>" placeholder="<?= $esc(Text::_('COM_DECAROMEMBERSHIP_PEOPLE_SEARCH_PLACEHOLDER')) ?>">
        <input type="hidden" id="jform_person_uuid" name="jform[person_uuid]" data-membership-person-target value="<?= $esc($personUuid) ?>">
        <div class="dm-people-results" data-membership-people-results></div>
      </div>

      <div class="dm-field">
        <label for="jform_scope"><?= Text::_('COM_DECAROMEMBERSHIP_FIELD_CARD_SCOPE') ?> *</label>
        <select id="jform_scope" name="jform[scope]" required data-membership-card-scope>
          <option value="association"<?= $scope==='association'?' selected':'' ?>><?= Text::_('COM_DECAROMEMBERSHIP_CARD_SCOPE_ASSOCIATION') ?></option>
          <option value="competition"<?= $scope==='competition'?' selected':'' ?>><?= Text::_('COM_DECAROMEMBERSHIP_CARD_SCOPE_COMPETITION') ?></option>
        </select>
      </div>

      <?= $renderField('season',$this->config['fields']['season'],$this->item->season??'') ?>

      <?php
        $issuerUuid=strtolower(trim((string)($this->item->issuer_organization_uuid??'')));
        $issuerName=trim((string)($this->selectedOrganization['name']??''));
        $issuerShort=trim((string)($this->selectedOrganization['short_name']??($this->selectedOrganization['code']??'')));
        $issuerLabel=$issuerName;
        if($issuerLabel!=='' && $issuerShort!=='' && strcasecmp($issuerLabel,$issuerShort)!==0) $issuerLabel.=' ('.$issuerShort.')';

        if($scope==='competition' && is_array($this->selectedCompetitionSeason)){
            $seasonIssuerUuid=strtolower(trim((string)($this->selectedCompetitionSeason['rights_holder_organization_uuid']??'')));
            if($seasonIssuerUuid!=='') $issuerUuid=$seasonIssuerUuid;

            $seasonIssuerName=trim((string)($this->selectedCompetitionSeason['rights_holder_name']??''));
            $seasonIssuerShort=trim((string)($this->selectedCompetitionSeason['rights_holder_short_name']??''));
            if($seasonIssuerName!==''){
                $issuerLabel=$seasonIssuerName;
                if($seasonIssuerShort!=='' && strcasecmp($issuerLabel,$seasonIssuerShort)!==0) $issuerLabel.=' ('.$seasonIssuerShort.')';
            }
        }

        if($issuerLabel==='' && $this->selectedCompetitionSeasonId>0){
            foreach($this->competitionSeasonOptions as $seasonOption){
                if((int)($seasonOption['id']??0)!==$this->selectedCompetitionSeasonId) continue;
                $rightsHolderName=trim((string)($seasonOption['rights_holder_name']??''));
                $rightsHolderShort=trim((string)($seasonOption['rights_holder_short_name']??''));
                $issuerLabel=$rightsHolderName;
                if($issuerLabel!=='' && $rightsHolderShort!=='' && strcasecmp($issuerLabel,$rightsHolderShort)!==0) $issuerLabel.=' ('.$rightsHolderShort.')';
                break;
            }
        }
      ?>
      <div class="dm-field dm-field-wide" data-membership-issuer-picker>
        <label for="membership_card_issuer_search"><?= Text::_('COM_DECAROMEMBERSHIP_FIELD_ISSUER_ORGANIZATION') ?> *</label>
        <input type="hidden" id="jform_issuer_organization_uuid" name="jform[issuer_organization_uuid]" data-membership-issuer-target value="<?= $esc($issuerUuid) ?>">

        <div data-membership-issuer-association<?= $scope==='competition'?' hidden':'' ?>>
          <?php if($this->organizationsAvailable): ?>
            <input
              type="search"
              id="membership_card_issuer_search"
              data-membership-issuer-search
              data-selected-label="<?= $esc($issuerLabel) ?>"
              autocomplete="off"
              value="<?= $esc($issuerLabel) ?>"
              placeholder="<?= $esc(Text::_('COM_DECAROMEMBERSHIP_ORGANIZATION_SEARCH_PLACEHOLDER')) ?>"
            >
            <div class="dm-people-results" data-membership-issuer-results></div>
            <small class="dm-muted"><?= Text::_('COM_DECAROMEMBERSHIP_CARD_ISSUER_ASSOCIATION_HELP') ?></small>
          <?php elseif($issuerUuid!==''): ?>
            <div class="alert alert-warning mb-0"><?= Text::_('COM_DECAROMEMBERSHIP_ORGANIZATION_LINK_PRESERVED') ?></div>
          <?php else: ?>
            <div class="alert alert-warning mb-0"><?= Text::_('COM_DECAROMEMBERSHIP_CARD_ISSUER_UNAVAILABLE') ?></div>
          <?php endif; ?>
        </div>

        <div class="dm-readonly-field" data-membership-issuer-competition<?= $scope==='competition'?'':' hidden' ?>>
          <strong data-membership-issuer-competition-label data-empty-label="<?= $esc(Text::_('COM_DECAROMEMBERSHIP_CARD_ISSUER_AUTOMATIC_PENDING')) ?>"><?= $esc($issuerLabel!==''?$issuerLabel:Text::_('COM_DECAROMEMBERSHIP_CARD_ISSUER_AUTOMATIC_PENDING')) ?></strong>
          <small class="dm-muted"><?= Text::_('COM_DECAROMEMBERSHIP_CARD_ISSUER_AUTOMATIC_HELP') ?></small>
        </div>
      </div>

      <?php
        $cardNumber=trim((string)($this->item->card_number??''));
        $numberingPolicy=$this->cardNumberingPolicy ?: ['numbering_mode'=>($scope==='competition'?'automatic':'manual'),'manual_edit'=>($scope==='association'?1:0),'source'=>'','sequence_padding'=>7];
        $numberingMode=trim((string)($numberingPolicy['numbering_mode']??'manual'));
        $numberingManualEdit=!empty($numberingPolicy['manual_edit']);
        $numberingSource=trim((string)($numberingPolicy['source']??''));
        $numberingAutomatic=$numberingMode==='automatic';
        $numberingExternal=$numberingMode==='external';
        $numberingEditable=$numberingMode==='manual' || ($numberingExternal && $numberingManualEdit);
      ?>
      <div
        class="dm-field dm-field-wide"
        data-membership-card-number-field
        data-numbering-mode="<?= $esc($numberingMode) ?>"
        data-numbering-manual-edit="<?= $numberingManualEdit?'1':'0' ?>"
        data-numbering-source="<?= $esc($numberingSource) ?>"
        data-initial-scope="<?= $esc($scope) ?>"
        data-automatic-placeholder="<?= $esc(Text::_('COM_DECAROMEMBERSHIP_CARD_NUMBER_AUTOMATIC_PLACEHOLDER')) ?>"
        data-external-placeholder="<?= $esc(Text::_('COM_DECAROMEMBERSHIP_CARD_NUMBER_EXTERNAL_PLACEHOLDER')) ?>"
        data-automatic-help="<?= $esc(Text::_('COM_DECAROMEMBERSHIP_CARD_NUMBER_AUTOMATIC_HELP')) ?>"
        data-external-help-template="<?= $esc(Text::_('COM_DECAROMEMBERSHIP_CARD_NUMBER_EXTERNAL_HELP')) ?>"
        data-external-source-generic="<?= $esc(Text::_('COM_DECAROMEMBERSHIP_NUMBER_SOURCE_EXTERNAL_GENERIC')) ?>"
      >
        <label for="jform_card_number"><?= Text::_('COM_DECAROMEMBERSHIP_FIELD_CARD_NUMBER') ?></label>
        <input
          type="text"
          id="jform_card_number"
          name="jform[card_number]"
          value="<?= $esc($cardNumber) ?>"
          data-membership-card-number-manual
          <?= $numberingEditable?'':' disabled' ?>
          <?= $numberingEditable?'':' hidden' ?>
        >
        <input
          type="text"
          id="membership_card_number_auto"
          value="<?= $esc($cardNumber) ?>"
          data-membership-card-number-auto
          placeholder="<?= $esc($numberingExternal ? Text::_('COM_DECAROMEMBERSHIP_CARD_NUMBER_EXTERNAL_PLACEHOLDER') : Text::_('COM_DECAROMEMBERSHIP_CARD_NUMBER_AUTOMATIC_PLACEHOLDER')) ?>"
          readonly
          <?= $numberingEditable?' hidden':'' ?>
        >
        <small class="dm-muted" data-membership-card-number-auto-help<?= $numberingEditable?' hidden':'' ?>>
          <?php if($numberingExternal): ?>
            <?= $esc(Text::sprintf('COM_DECAROMEMBERSHIP_CARD_NUMBER_EXTERNAL_HELP', $numberingSource!==''?$numberingSource:Text::_('COM_DECAROMEMBERSHIP_NUMBER_SOURCE_EXTERNAL_GENERIC'))) ?>
          <?php else: ?>
            <?= Text::_('COM_DECAROMEMBERSHIP_CARD_NUMBER_AUTOMATIC_HELP') ?>
          <?php endif; ?>
        </small>
      </div>
      <?= $renderField('status',$this->config['fields']['status'],$status) ?>
      <?= $renderField('valid_from',$this->config['fields']['valid_from'],$this->item->valid_from??'') ?>
      <?= $renderField('expires_at',$this->config['fields']['expires_at'],$this->item->expires_at??'') ?>
    </div>
  </section>

  <details class="dm-card dm-member-advanced">
    <summary><?= Text::_('COM_DECAROMEMBERSHIP_CARD_ADVANCED') ?></summary>
    <p class="dm-muted"><?= Text::_('COM_DECAROMEMBERSHIP_CARD_ADVANCED_DESC') ?></p>
    <div class="dm-form-grid">
      <?= $renderField('type',$this->config['fields']['type'],$this->item->type??'electronic') ?>
      <?= $renderField('notes',$this->config['fields']['notes'],$this->item->notes??'') ?>
    </div>
  </details>
<?php elseif($isMember): ?>
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

  <?php if((int)($this->item->id??0)>0): ?>
  <section class="dm-card dm-member-card-summary">
    <div class="dm-section-head">
      <div>
        <h2><?= Text::_('COM_DECAROMEMBERSHIP_MEMBER_CARD_SUMMARY') ?></h2>
        <p class="dm-muted mb-0"><?= Text::_('COM_DECAROMEMBERSHIP_MEMBER_CARD_SUMMARY_DESC') ?></p>
      </div>
      <?php if($this->currentMemberCard): ?>
        <a class="btn btn-sm btn-outline-primary" href="<?= Route::_('index.php?option=com_decaromembership&view=records&entity=cards&filter_search='.rawurlencode((string)$this->currentMemberCard->card_number)) ?>">
          <?= Text::_('COM_DECAROMEMBERSHIP_MEMBER_CARD_MANAGE') ?>
        </a>
      <?php else: ?>
        <a class="btn btn-sm btn-outline-primary" href="<?= Route::_('index.php?option=com_decaromembership&view=record&entity=cards&id=0&member_id='.(int)($this->item->id??0)) ?>">
          <?= Text::_('COM_DECAROMEMBERSHIP_MEMBER_CARD_CREATE') ?>
        </a>
      <?php endif; ?>
    </div>
    <?php if($this->currentMemberCard):
        $cardStatus=trim((string)($this->currentMemberCard->status??''));
        $cardType=trim((string)($this->currentMemberCard->type??''));
        $statusLabel=Text::_($cardStatusKey[$cardStatus]??$cardStatus);
        $typeLabel=Text::_($cardTypeKey[$cardType]??$cardType);
    ?>
      <div class="dm-person-summary">
        <strong><?= $esc($this->currentMemberCard->card_number??'') ?></strong>
        <div class="dm-person-meta">
          <?php if($typeLabel!==''): ?><span><?= $esc($typeLabel) ?></span><?php endif; ?>
          <?php if($statusLabel!==''): ?><span><?= $esc($statusLabel) ?></span><?php endif; ?>
          <?php if(!empty($this->currentMemberCard->annual_mark)): ?><span><?= Text::_('COM_DECAROMEMBERSHIP_FIELD_ANNUAL_MARK') ?>: <?= $esc($this->currentMemberCard->annual_mark) ?></span><?php endif; ?>
        </div>
      </div>
    <?php elseif($this->legacyCardNumber!==''): ?>
      <div class="alert alert-warning mb-0">
        <?= Text::sprintf('COM_DECAROMEMBERSHIP_MEMBER_CARD_LEGACY', $esc($this->legacyCardNumber)) ?>
      </div>
    <?php else: ?>
      <p class="dm-muted mb-0"><?= Text::_('COM_DECAROMEMBERSHIP_MEMBER_CARD_NONE') ?></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

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
<?php elseif(!$isCard): ?>
  <div class="dm-card dm-form-grid">
  <?php foreach($this->config['fields'] as $name=>$field):
      $value=$this->item->$name??($field['default']??'');
      if(($field['type']??'')==='relation'): ?>
        <div class="dm-field"><label for="jform_<?= $esc($name) ?>"><?= Text::_($field['label']) ?><?= ($field['required']??false)?' *':'' ?></label><select id="jform_<?= $esc($name) ?>" name="jform[<?= $esc($name) ?>]"<?= ($field['required']??false)?' required':'' ?>><option value="">-</option><?php foreach($this->relations[$name]??[] as $opt): ?><option value="<?= (int)$opt->id ?>"<?php if(isset($opt->organization_uuid)): ?> data-organization-uuid="<?= $esc(strtolower(trim((string)$opt->organization_uuid))) ?>"<?php endif; ?><?= (int)$value===(int)$opt->id?' selected':'' ?>><?= $esc($opt->title) ?></option><?php endforeach; ?></select></div>
      <?php else: echo $renderField($name,$field,$value); endif; ?>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<input type="hidden" name="id" value="<?= (int)($this->item->id??0) ?>"><input type="hidden" name="entity" value="<?= $esc($this->entity) ?>"><input type="hidden" name="task" value=""><?= HTMLHelper::_('form.token') ?></form>