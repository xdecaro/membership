#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import re
import zipfile
from pathlib import Path
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
VERSION = (ROOT / 'VERSION').read_text(encoding='utf-8').strip()


def fail(msg):
    raise SystemExit('ERROR: ' + msg)


def version(path):
    return (ET.parse(path).getroot().findtext('version') or '').strip()


def require(path, *markers):
    text = (ROOT / path).read_text(encoding='utf-8')
    for marker in markers:
        if marker not in text:
            fail(f'{path} missing {marker}')
    return text


def validate_private_boundaries():
    for runtime_root in ('component', 'plugins', 'package'):
        for path in (ROOT / runtime_root).rglob('*'):
            if not path.is_file():
                continue
            text = path.read_text(encoding='utf-8', errors='ignore')
            lowered = text.lower()
            if '#__decarofinance_' in text:
                fail(f'Membership runtime accesses Finance private table: {path.relative_to(ROOT)}')
            if '\\component\\decarofinance\\' in lowered:
                fail(f'Membership runtime depends on Finance implementation class: {path.relative_to(ROOT)}')
            if '#__xdecaropeople_' in text:
                fail(f'Membership runtime accesses People private table: {path.relative_to(ROOT)}')
            if '#__xdecaroorganizations_' in text:
                fail(f'Membership runtime accesses Organizations private table: {path.relative_to(ROOT)}')
            if re.search(r'People\\Administrator\\(?:Model|Table)\\', text, re.I):
                fail(f'Membership runtime depends on private People Model/Table: {path.relative_to(ROOT)}')
            if re.search(r'Organizations\\Administrator\\(?:Model|Table)\\', text, re.I):
                fail(f'Membership runtime depends on private Organizations Model/Table: {path.relative_to(ROOT)}')


def validate_finance_boundary():
    require(
        'component/admin/src/Service/CrossProductIntegrationService.php',
        "bootComponent('com_decarofinance')",
        'getFinanceService',
        'upsertObligation',
        'upsertPayment',
        'allocatePaymentIdempotent',
    )
    require('tests/finance-integration-contract.php', 'com_decarofinance', 'getFinanceService', 'allocatePaymentIdempotent')
    require('tests/finance-runtime.php', 'MEMBERSHIP_JOOMLA_ROOT', 'syncDueToFinance', 'syncPaymentToFinance', 'syncPaidPaymentAllocation')


def validate_people_boundary():
    require(
        'component/admin/src/Service/PeopleIntegrationService.php',
        "MINIMUM_CORE_VERSION = '2.0.1'",
        "MINIMUM_PEOPLE_VERSION = '1.2.15'",
        "bootComponent('com_xdecaropeople')",
        'getPersonProviderService',
        'getPeopleByUuids',
    )
    require(
        'component/admin/src/Service/MemberPeopleLinkService.php',
        'validateForSave',
        'linkLegacyMember',
        'relinkMember',
        'stripPeopleOwnedFields',
    )
    require(
        'component/admin/src/Service/MemberPeopleBackfillService.php',
        'loadUnlinkedMembersWithUserId',
        'findByUserIdUnique',
        'people_backfill',
    )
    require('component/admin/src/Model/RecordsModel.php', 'resolvePeopleForItems', 'searchPeople($search, 200)', 'getPeopleByUuids')
    require('component/admin/src/Controller/PeopleController.php', 'searchPeople($q, 20)', 'relinkMember', 'JsonResponse')


def validate_organizations_boundary():
    require(
        'component/admin/src/Service/OrganizationsIntegrationService.php',
        "bootComponent('com_xdecaroorganizations')",
        'getOrganizationProviderService',
        'searchOrganizations',
        'validateOptionalUuid',
    )
    require(
        'component/admin/src/Model/RecordModel.php',
        'OrganizationsIntegrationService',
        'validateOptionalUuid',
        "array_key_exists('organization_uuid', $input)",
    )
    require(
        'component/admin/tmpl/record/default.php',
        'COM_DECAROMEMBERSHIP_MEMBER_ESSENTIALS',
        'COM_DECAROMEMBERSHIP_ORGANIZATION_NONE',
        'COM_DECAROMEMBERSHIP_MEMBER_ADVANCED',
        'COM_DECAROMEMBERSHIP_LOCATION_LEGACY_HELP',
        'data-membership-organization-search',
        'data-membership-organization-select',
        'organizationTypeLabel',
        "organization['depth']",
        "organization['path']",
    )
    require(
        'component/admin/src/View/Record/HtmlView.php',
        'buildOrganizationOptions',
        "organization['parent_id']",
        "organization['depth']",
        "organization['path']",
    )
    require(
        'component/media/js/admin.js',
        'initOrganizationPicker',
        'data-membership-organization-search',
        'data-membership-organization-select',
        'option.hidden',
        'option.disabled',
    )
    require('tests/organizations-runtime.php', 'getOrganizationsIntegrationService', 'CI-SIMPLE-001', 'CI-ORG-001')


def validate_member_lifecycle_basics():
    require(
        'component/admin/src/Service/MemberLifecycleService.php',
        'prepareForSave',
        'isAutomaticNumbering',
        'generateNumber',
        'ACTIVE_STATUSES',
        'TERMINAL_STATUSES',
        'member_number_mode',
        'member_number_padding',
        'member_default_status',
    )
    require(
        'component/admin/src/Config/MemberCoreEntities.php',
        "'category_id'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CATEGORY','type'=>'relation','relation'=>'categories','required'=>true]",
        "'status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_STATUS','type'=>'select','required'=>true,'default'=>'pending'",
        "'code'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CODE','type'=>'text','unique'=>true]",
    )
    require(
        'component/admin/src/Model/RecordModel.php',
        'MemberLifecycleService',
        'prepareForSave',
        'generateNumber',
        'updateMemberNumber',
    )
    require(
        'component/admin/tmpl/record/default.php',
        'COM_DECAROMEMBERSHIP_MEMBER_CATEGORY_MISSING',
        'COM_DECAROMEMBERSHIP_MEMBER_CATEGORY_MANAGE',
        'COM_DECAROMEMBERSHIP_MEMBER_NUMBER_AUTOMATIC_PLACEHOLDER',
        'COM_DECAROMEMBERSHIP_FIRST_REGISTRATION_HELP',
    )
    require(
        'component/admin/config.xml',
        'member_number_mode',
        'member_number_padding',
        'member_default_status',
    )
    require('tests/membership-1.9.0-member-lifecycle-contract.php', 'member lifecycle basics contract')


def validate_ordering_defaults():
    marker = "'ordering'=>['label'=>'JFIELD_ORDERING_LABEL','type'=>'number','default'=>0]"
    require('component/admin/src/Config/MemberCoreEntities.php', marker)
    require('component/admin/src/Config/CaseEntities.php', marker)
    require('component/admin/src/Config/OperationsEntities.php', marker)
    schema_marker = ROOT / 'component/admin/sql/updates/mysql/1.9.1.sql'
    if not schema_marker.is_file():
        fail('Membership 1.9.1 schema marker missing')
    if re.search(r'\\b(?:DROP\\s+TABLE|TRUNCATE\\s+TABLE)\\b', schema_marker.read_text(), re.I):
        fail('Membership 1.9.1 schema marker must be non-destructive')


def validate_published_defaults():
    marker = "'published'=>['label'=>'JSTATUS','type'=>'published','default'=>1]"
    for path in (
        'component/admin/src/Config/MemberCoreEntities.php',
        'component/admin/src/Config/CaseEntities.php',
        'component/admin/src/Config/OperationsEntities.php',
        'component/admin/src/Config/OrganizationEntities.php',
        'component/admin/src/Config/FinanceEntities.php',
    ):
        require(path, marker)
    require('component/admin/tmpl/records/default.php', "'JPUBLISHED'", "'JUNPUBLISHED'", "==='published'")
    schema_marker = ROOT / 'component/admin/sql/updates/mysql/1.9.2.sql'
    if not schema_marker.is_file():
        fail('Membership 1.9.2 schema marker missing')
    if re.search(r'\\b(?:DROP\\s+TABLE|TRUNCATE\\s+TABLE)\\b', schema_marker.read_text(), re.I):
        fail('Membership 1.9.2 schema marker must be non-destructive')


def validate_legacy_lifecycle_safety():
    require(
        'component/admin/src/Config/MemberCoreEntities.php',
        "'published'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PUBLISHED','type'=>'published','default'=>1]",
    )
    require(
        'component/admin/src/Service/MemberLifecycleService.php',
        '$legacyBootstrap',
        "oldStatus === ''",
        '!$legacyBootstrap',
    )
    require(
        'tests/member-lifecycle-runtime.php',
        'CI Legacy Blank Status',
        'Legacy blank-status bootstrap invented lifecycle date',
        'New member did not default to published=1.',
    )
    require(
        'tests/membership-1.9.3-legacy-lifecycle-contract.php',
        'legacy lifecycle contract',
    )
    schema_marker = ROOT / 'component/admin/sql/updates/mysql/1.9.3.sql'
    if not schema_marker.is_file():
        fail('Membership 1.9.3 schema marker missing')
    if re.search(r'\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b', schema_marker.read_text(), re.I):
        fail('Membership 1.9.3 schema marker must be non-destructive')


def validate_card_source():
    require(
        'component/admin/src/Config/MemberCoreEntities.php',
        "'search'=>['first_name','last_name','tax_code','member_number','email']",
        "'list'=>['member_number','last_name','first_name','category_id','status','email']",
    )
    member_config = (ROOT / 'component/admin/src/Config/MemberCoreEntities.php').read_text()
    if "'card_number'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CARD_NUMBER','type'=>'text','unique'=>true]" in member_config:
        fail('Member record must not expose legacy card_number as an editable field')
    require(
        'component/admin/src/Service/RecordRepository.php',
        'loadCurrentMemberCard',
        '#__decaromembership_cards',
    )
    require(
        'component/admin/src/View/Record/HtmlView.php',
        'currentMemberCard',
        'legacyCardNumber',
    )
    require(
        'component/admin/src/Model/RecordModel.php',
        'getCurrentMemberCard',
    )
    require(
        'component/admin/tmpl/record/default.php',
        'COM_DECAROMEMBERSHIP_MEMBER_CARD_SUMMARY',
        'COM_DECAROMEMBERSHIP_MEMBER_CARD_MANAGE',
        'COM_DECAROMEMBERSHIP_MEMBER_CARD_LEGACY',
        'entity=cards',
    )
    require(
        'tests/membership-1.9.4-card-source-contract.php',
        'card source contract',
    )
    schema_marker = ROOT / 'component/admin/sql/updates/mysql/1.9.4.sql'
    if not schema_marker.is_file():
        fail('Membership 1.9.4 schema marker missing')
    if re.search(r'\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b', schema_marker.read_text(), re.I):
        fail('Membership 1.9.4 schema marker must be non-destructive')


def validate_member_card_view():
    require(
        'component/admin/src/Model/RecordModel.php',
        'public function getCurrentMemberCard(int $memberId): ?object',
        'return $this->repository()->loadCurrentMemberCard($memberId);',
    )
    require(
        'component/admin/src/View/Record/HtmlView.php',
        '$model->getCurrentMemberCard($memberId)',
    )
    view = (ROOT / 'component/admin/src/View/Record/HtmlView.php').read_text()
    if '$this->getDatabase()' in view:
        fail('Record HtmlView must not call undefined getDatabase()')
    if 'new RecordRepository(' in view:
        fail('Record HtmlView must not construct RecordRepository directly')
    require(
        'tests/membership-1.9.5-member-card-view-contract.php',
        'member card view contract',
    )
    schema_marker = ROOT / 'component/admin/sql/updates/mysql/1.9.5.sql'
    if not schema_marker.is_file():
        fail('Membership 1.9.5 schema marker missing')
    if re.search(r'\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b', schema_marker.read_text(), re.I):
        fail('Membership 1.9.5 schema marker must be non-destructive')


def validate_card_member_flow():
    require(
        'component/admin/src/Model/RecordModel.php',
        'getRelationOptions(string $entity, int $includeId = 0)',
        'new PeopleIntegrationService($db)',
        'COM_DECAROMEMBERSHIP_MEMBER_FALLBACK_LABEL',
    )
    require(
        'component/admin/src/View/Record/HtmlView.php',
        "$this->entity === 'cards'",
        "$app->input->getInt('member_id')",
        "getRelationOptions($field['relation'], $selectedId)",
    )
    require(
        'component/admin/tmpl/record/default.php',
        'view=record&entity=cards&id=0&member_id=',
        'COM_DECAROMEMBERSHIP_MEMBER_CARD_CREATE',
    )
    require(
        'component/admin/src/Model/RecordsModel.php',
        'getPeopleByUuids',
        'display_name',
    )
    require(
        'tests/membership-1.9.6-card-member-flow-contract.php',
        'card member flow contract',
    )
    schema_marker = ROOT / 'component/admin/sql/updates/mysql/1.9.6.sql'
    if not schema_marker.is_file():
        fail('Membership 1.9.6 schema marker missing')
    if re.search(r'\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b', schema_marker.read_text(), re.I):
        fail('Membership 1.9.6 schema marker must be non-destructive')


def validate_card_labels():
    require(
        'component/admin/src/Config/CaseEntities.php',
        "'status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CARD_STATUS'",
        "'published'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_PUBLISHED','type'=>'published','default'=>1]",
        'COM_DECAROMEMBERSHIP_CARD_STATUS_ACTIVE',
        'COM_DECAROMEMBERSHIP_CARD_STATUS_EXPIRED',
        'COM_DECAROMEMBERSHIP_CARD_STATUS_LOST',
    )
    require(
        'component/admin/language/it-IT/com_decaromembership.ini',
        'COM_DECAROMEMBERSHIP_FIELD_CARD_STATUS="Stato tessera"',
        'COM_DECAROMEMBERSHIP_CARD_STATUS_ACTIVE="Attiva"',
        'COM_DECAROMEMBERSHIP_CARD_STATUS_EXPIRED="Scaduta"',
        'COM_DECAROMEMBERSHIP_CARD_STATUS_LOST="Smarrita"',
        'COM_DECAROMEMBERSHIP_FIELD_PUBLISHED="Pubblicato"',
    )
    require(
        'component/admin/tmpl/records/default.php',
        "($field['type']??'')==='select'",
        "Text::_($field['options'][(string)$value])",
    )
    require(
        'tests/membership-1.9.7-card-labels-contract.php',
        'card labels contract',
    )
    schema_marker = ROOT / 'component/admin/sql/updates/mysql/1.9.7.sql'
    if not schema_marker.is_file():
        fail('Membership 1.9.7 schema marker missing')
    if re.search(r'\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b', schema_marker.read_text(), re.I):
        fail('Membership 1.9.7 schema marker must be non-destructive')


def validate_card_required_fields():
    require(
        'component/admin/src/Config/CaseEntities.php',
        "'type'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CARD_TYPE','type'=>'select','required'=>true",
        "'status'=>['label'=>'COM_DECAROMEMBERSHIP_FIELD_CARD_STATUS','type'=>'select','required'=>true",
    )
    require(
        'tests/member-lifecycle-runtime.php',
        'Card creation without type must be rejected.',
        'Card creation without status must be rejected.',
    )
    require(
        'tests/membership-1.9.8-card-required-fields-contract.php',
        'card required fields contract',
    )
    schema_marker = ROOT / 'component/admin/sql/updates/mysql/1.9.8.sql'
    if not schema_marker.is_file():
        fail('Membership 1.9.8 schema marker missing')
    if re.search(r'\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b', schema_marker.read_text(), re.I):
        fail('Membership 1.9.8 schema marker must be non-destructive')


def validate_validation_preservation():
    require(
        'component/admin/src/Controller/RecordController.php',
        "setUserState('com_decaromembership.record.'",
        "$data=(array)$this->input->get('jform',[],'array')",
    )
    require(
        'component/admin/src/Model/RecordModel.php',
        'getUserState($stateKey)',
        'setUserState($stateKey, null)',
        '$item = (object) $submitted',
    )
    require(
        'component/admin/src/Service/RecordValidator.php',
        "Text::_((string) ($field['label'] ?? $name))",
        "Text::sprintf('COM_DECAROMEMBERSHIP_ERROR_REQUIRED', $label)",
    )
    require(
        'tests/membership-1.9.9-validation-preserve-form-contract.php',
        'validation preservation contract',
    )
    schema_marker = ROOT / 'component/admin/sql/updates/mysql/1.9.9.sql'
    if not schema_marker.is_file():
        fail('Membership 1.9.9 schema marker missing')
    if re.search(r'\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DROP\s+COLUMN)\b', schema_marker.read_text(), re.I):
        fail('Membership 1.9.9 schema marker must be non-destructive')


def validate():
    if VERSION != '1.9.9':
        fail(f'unexpected VERSION {VERSION!r}')

    manifests = [
        ROOT / 'component/decaromembership.xml',
        ROOT / 'package/pkg_decaromembership.xml',
        ROOT / 'plugins/xdecaroanalytics/decaromembership/decaromembership.xml',
        ROOT / 'plugins/task/decaromembership/decaromembership.xml',
    ]
    for path in manifests:
        ET.parse(path)
        if version(path) != VERSION:
            fail(f'{path.relative_to(ROOT)} version mismatch')

    root = ET.parse(manifests[0]).getroot()
    if root.find('./files') is not None:
        fail('Membership remains administrator-only')
    if (root.find('targetplatform').get('version') or '') != '6.*':
        fail('Membership must target Joomla 6 only')
    for sql in root.findall('./install/sql/file') + root.findall('./uninstall/sql/file'):
        if (sql.get('driver') or '') != 'mysql' or (sql.get('charset') or '') != 'utf8':
            fail('Joomla SQL manifest entries must use mysql/utf8')

    assets = json.loads((ROOT / 'component/media/joomla.asset.json').read_text())
    if assets.get('version') != VERSION:
        fail('asset version mismatch')
    for asset in assets.get('assets', []):
        if asset.get('version') != VERSION:
            fail(f"asset {asset.get('name')} version mismatch")

    package_root = ET.parse(manifests[1]).getroot()
    if (package_root.find('targetplatform').get('version') or '') != '6.*':
        fail('Membership package must target Joomla 6 only')
    children = {
        (n.get('type', ''), n.get('id', ''), n.get('group', ''), (n.text or '').strip())
        for n in package_root.findall('./files/file')
    }
    expected = {
        ('component', 'com_decaromembership', '', 'com_decaromembership.zip'),
        ('plugin', 'decaromembership', 'xdecaroanalytics', 'plg_xdecaroanalytics_decaromembership.zip'),
        ('plugin', 'decaromembership', 'task', 'plg_task_decaromembership.zip'),
    }
    if children != expected:
        fail(f'package children mismatch: {children}')

    validate_private_boundaries()
    validate_finance_boundary()
    validate_people_boundary()
    validate_organizations_boundary()
    validate_member_lifecycle_basics()
    validate_ordering_defaults()
    validate_published_defaults()
    validate_legacy_lifecycle_safety()
    validate_card_source()
    validate_member_card_view()
    validate_card_member_flow()
    validate_card_labels()
    validate_card_required_fields()
    validate_validation_preservation()

    require(
        'component/admin/src/Service/CoreIntegrationService.php',
        "COMPONENT='com_decaromembership'",
        'CapabilityRegistry',
        'membership.analytics.provider',
        'membership.notifications.bridge',
        'membership.tasks.bridge',
        'membership.reminders.process',
    )
    bridge = require(
        'component/admin/src/Service/CrossProductIntegrationService.php',
        'com_xdecaronotifications', 'getNotificationService', 'com_xdecarotasks', 'getTaskService', 'source_component',
    )
    if '#__xdecaronotifications_' in bridge or '#__xdecarotasks_' in bridge:
        fail('cross-product bridge accesses private tables')

    require('component/admin/src/Service/AnalyticsSourceService.php', 'assertAuthorised', '#__decaromembership_members', 'membership.members.total', 'membership.expiring')
    require('component/admin/src/Service/ReminderService.php', '#__decaromembership_renewals', '#__decaromembership_cards', '#__decaromembership_documents', '#__decaromembership_dues', 'external_key', 'integration_manager_user_id')
    require('plugins/xdecaroanalytics/decaromembership/src/Extension/Decaromembership.php', 'RegisterProvidersEvent::NAME', 'getAnalyticsSourceService')
    provider = require('plugins/xdecaroanalytics/decaromembership/src/Provider/MembershipProvider.php', 'implements AnalyticsProviderInterface', "return 'membership'")
    if '#__decaromembership_' in provider:
        fail('Analytics adapter must delegate to Membership source service')
    require('plugins/task/decaromembership/src/Extension/Decaromembership.php', 'TaskPluginTrait', 'decaromembership.reminders', 'getReminderService')
    require('component/admin/services/provider.php', 'MembershipComponent', 'PeopleIntegrationService::class', 'OrganizationsIntegrationService::class', 'AnalyticsSourceService::class', 'ReminderService::class', 'setPeopleIntegrationService', 'setOrganizationsIntegrationService', 'setReminderService')

    installer = require(
        'package/script.php',
        "MINIMUM_CORE_VERSION = '2.0.1'",
        "MINIMUM_PEOPLE_VERSION = '1.2.15'",
        "['install', 'update', 'discover_install']",
        "['install', 'discover_install']",
        'MemberPeopleBackfillService',
        "'xdecaroanalytics'",
        "'task'",
        'ParameterType::INTEGER',
    )
    if installer.index("['install', 'discover_install']") < installer.index("'xdecaroanalytics'"):
        pass
    else:
        fail('optional plugin enablement must remain install/discover-only')

    schema_marker = ROOT / 'component/admin/sql/updates/mysql/1.5.0.sql'
    if not schema_marker.is_file():
        fail('Membership 1.5.0 schema update missing')
    update_sql = schema_marker.read_text(encoding='utf-8')
    for marker in ('person_uuid', 'uq_member_person_uuid', 'MODIFY `first_name` VARCHAR(190) NULL', 'MODIFY `last_name` VARCHAR(190) NULL'):
        if marker not in update_sql:
            fail(f'1.5.0 schema missing {marker}')

    lifecycle_marker = ROOT / 'component/admin/sql/updates/mysql/1.6.0.sql'
    if not lifecycle_marker.is_file():
        fail('Membership 1.6.0 schema update missing')
    lifecycle_sql = lifecycle_marker.read_text(encoding='utf-8')
    for marker in ('application_date', 'admission_date', 'current_membership_start_date', 'seniority_credit_days', 'status_effective_date', 'cessation_date', 'voting_active', 'voting_passive', '#__decaromembership_member_history'):
        if marker not in lifecycle_sql:
            fail(f'1.6.0 schema missing {marker}')

    asset_marker = ROOT / 'component/admin/sql/updates/mysql/1.6.1.sql'
    if not asset_marker.is_file():
        fail('Membership 1.6.1 schema marker missing')

    registry_marker = ROOT / 'component/admin/sql/updates/mysql/1.6.2.sql'
    if not registry_marker.is_file():
        fail('Membership 1.6.2 schema marker missing')

    direct_asset_marker = ROOT / 'component/admin/sql/updates/mysql/1.6.3.sql'
    if not direct_asset_marker.is_file():
        fail('Membership 1.6.3 schema marker missing')

    simple_member_marker = ROOT / 'component/admin/sql/updates/mysql/1.7.0.sql'
    if not simple_member_marker.is_file():
        fail('Membership 1.7.0 schema update missing')
    simple_member_sql = simple_member_marker.read_text(encoding='utf-8')
    for marker in ('organization_uuid', 'old_organization_uuid', 'new_organization_uuid'):
        if marker not in simple_member_sql:
            fail(f'1.7.0 schema missing {marker}')

    organization_picker_marker = ROOT / 'component/admin/sql/updates/mysql/1.8.0.sql'
    if not organization_picker_marker.is_file():
        fail('Membership 1.8.0 schema marker missing')

    lifecycle_basics_marker = ROOT / 'component/admin/sql/updates/mysql/1.9.0.sql'
    if not lifecycle_basics_marker.is_file():
        fail('Membership 1.9.0 schema update missing')
    lifecycle_basics_sql = lifecycle_basics_marker.read_text(encoding='utf-8')
    for marker in ('code', 'uq_category_code'):
        if marker not in lifecycle_basics_sql:
            fail(f'1.9.0 schema missing {marker}')

    for media_path in (
        ROOT / 'component/media/css/admin.css',
        ROOT / 'component/media/css/core-bridge.css',
        ROOT / 'component/media/js/admin.js',
        ROOT / 'component/media/joomla.asset.json',
    ):
        if not media_path.is_file():
            fail(f'missing Membership media asset: {media_path.relative_to(ROOT)}')

    asset_service = require(
        'component/admin/src/Service/AdminAssetService.php',
        "Uri::root(true)",
        "addStyleSheet(",
        "/css/admin.css",
        "addScript(",
        "/js/admin.js",
    )
    for view_path in (
        'component/admin/src/View/Dashboard/HtmlView.php',
        'component/admin/src/View/Records/HtmlView.php',
        'component/admin/src/View/Record/HtmlView.php',
        'component/admin/src/View/Information/HtmlView.php',
    ):
        require(view_path, 'AdminAssetService::useAssets')

    for template_path in (
        'component/admin/tmpl/dashboard/default.php',
        'component/admin/tmpl/records/default.php',
        'component/admin/tmpl/record/default.php',
        'component/admin/tmpl/information/default.php',
        'component/admin/tmpl/information/core.php',
    ):
        template_text = (ROOT / template_path).read_text(encoding='utf-8')
        if '<link rel="stylesheet"' in template_text or '<script src=' in template_text:
            fail(f'duplicate manual asset tag remains in {template_path}')

    install = (ROOT / 'component/admin/sql/install.mysql.utf8mb4.sql').read_text()
    for marker in ('#__decaromembership_notifications', '`person_uuid` CHAR(36) NULL', 'uq_member_person_uuid', '`organization_uuid` CHAR(36) NULL', 'idx_member_organization_uuid', '`code` VARCHAR(100) NULL', 'uq_category_code'):
        if marker not in install:
            fail(f'clean install schema missing {marker}')
    for sql in (ROOT / 'component/admin/sql').rglob('*.sql'):
        if re.search(r'\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b', sql.read_text(), re.I) and 'uninstall' not in sql.name:
            fail(f'destructive update SQL: {sql}')

    feed_update = ET.parse(ROOT / 'updates/pkg_decaromembership.xml').getroot().find('update')
    if feed_update is None:
        fail('update feed missing')
    feed_version = (feed_update.findtext('version') or '').strip()
    def parts(v): return tuple(int(p) for p in v.split('.'))
    if not feed_version or parts(feed_version) > parts(VERSION):
        fail('public update feed cannot be newer than source')
    expected_url = f'https://github.com/xdecaro/membership/releases/download/v{feed_version}/pkg_decaromembership_{feed_version}.zip'
    if (feed_update.findtext('./downloads/downloadurl') or '').strip() != expected_url:
        fail('update download mismatch')
    sha = (feed_update.findtext('sha256') or '').strip()
    if re.fullmatch(r'[0-9a-f]{64}', sha) is None:
        fail('update feed SHA-256 must be a 64-character lowercase hex digest')

    print(f'Membership {VERSION} source validation OK')


def validate_dist():
    dist = ROOT / 'dist'
    files = [
        dist / f'com_decaromembership_{VERSION}.zip',
        dist / f'plg_xdecaroanalytics_decaromembership_{VERSION}.zip',
        dist / f'plg_task_decaromembership_{VERSION}.zip',
        dist / f'pkg_decaromembership_{VERSION}.zip',
        dist / 'SHA256SUMS.txt',
    ]
    for path in files:
        if not path.is_file(): fail(f'missing {path.name}')

    with zipfile.ZipFile(files[3]) as archive:
        expected = {'pkg_decaromembership.xml','script.php','com_decaromembership.zip','plg_xdecaroanalytics_decaromembership.zip','plg_task_decaromembership.zip'}
        if set(archive.namelist()) != expected: fail('unexpected package contents')

        component_bytes = archive.read('com_decaromembership.zip')
        import io
        with zipfile.ZipFile(io.BytesIO(component_bytes)) as component_archive:
            required_component_assets = {
                'media/css/admin.css',
                'media/css/core-bridge.css',
                'media/js/admin.js',
                'media/joomla.asset.json',
            }
            missing = required_component_assets.difference(component_archive.namelist())
            if missing:
                fail(f'component package missing media assets: {sorted(missing)}')
    for path in files[:4]:
        with zipfile.ZipFile(path) as archive:
            if archive.testzip() is not None: fail(f'corrupt {path.name}')
    print(f'Membership {VERSION} dist validation OK')


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--dist', action='store_true')
    args = parser.parse_args()
    validate()
    if args.dist: validate_dist()


if __name__ == '__main__':
    main()
