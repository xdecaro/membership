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


def validate_finance_boundary():
    banned_table_prefix = '#__' + 'decarofinance_'
    banned_class_fragment = '\\component\\decarofinance\\'

    for runtime_root in ('component', 'plugins', 'package'):
        for path in (ROOT / runtime_root).rglob('*'):
            if not path.is_file():
                continue
            text = path.read_text(encoding='utf-8', errors='ignore')
            if banned_table_prefix in text:
                fail(f'Membership runtime accesses Finance private table: {path.relative_to(ROOT)}')
            if banned_class_fragment in text.lower():
                fail(f'Membership runtime depends on Finance implementation class: {path.relative_to(ROOT)}')

    require(
        'component/admin/src/Service/CrossProductIntegrationService.php',
        "bootComponent('com_decarofinance')",
        'getFinanceService',
        'upsertObligation',
        'upsertPayment',
        'allocatePaymentIdempotent',
    )
    require(
        'tests/finance-integration-contract.php',
        'com_decarofinance',
        'getFinanceService',
        'allocatePaymentIdempotent',
    )
    require(
        'tests/finance-runtime.php',
        'MEMBERSHIP_JOOMLA_ROOT',
        'syncDueToFinance',
        'syncPaymentToFinance',
        'syncPaidPaymentAllocation',
    )


def validate():
    if VERSION != '1.4.0':
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
    for sql in root.findall('./install/sql/file') + root.findall('./uninstall/sql/file'):
        if (sql.get('driver') or '') != 'mysql' or (sql.get('charset') or '') != 'utf8':
            fail('Joomla SQL manifest entries must use mysql/utf8')

    assets = json.loads((ROOT / 'component/media/joomla.asset.json').read_text())
    if assets.get('version') != VERSION:
        fail('asset version mismatch')

    package_root = ET.parse(manifests[1]).getroot()
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
        'com_xdecaronotifications',
        'getNotificationService',
        'com_xdecarotasks',
        'getTaskService',
        'source_component',
    )
    if '#__xdecaronotifications_' in bridge or '#__xdecarotasks_' in bridge:
        fail('cross-product bridge accesses private tables')

    validate_finance_boundary()

    require(
        'component/admin/src/Service/AnalyticsSourceService.php',
        'assertAuthorised',
        '#__decaromembership_members',
        'membership.members.total',
        'membership.expiring',
    )
    require(
        'component/admin/src/Service/ReminderService.php',
        '#__decaromembership_renewals',
        '#__decaromembership_cards',
        '#__decaromembership_documents',
        '#__decaromembership_dues',
        'external_key',
        'integration_manager_user_id',
    )
    require(
        'plugins/xdecaroanalytics/decaromembership/src/Extension/Decaromembership.php',
        'RegisterProvidersEvent::NAME',
        'getAnalyticsSourceService',
    )
    provider = require(
        'plugins/xdecaroanalytics/decaromembership/src/Provider/MembershipProvider.php',
        'implements AnalyticsProviderInterface',
        "return 'membership'",
    )
    if '#__decaromembership_' in provider:
        fail('Analytics adapter must delegate to Membership source service')
    require(
        'plugins/task/decaromembership/src/Extension/Decaromembership.php',
        'TaskPluginTrait',
        'decaromembership.reminders',
        'getReminderService',
    )
    require(
        'component/admin/services/provider.php',
        'MembershipComponent',
        'AnalyticsSourceService::class',
        'ReminderService::class',
        'setReminderService',
    )

    installer = require(
        'package/script.php',
        "['install', 'discover_install']",
        "'xdecaroanalytics'",
        "'task'",
        'decaromembership',
        'ParameterType::INTEGER',
    )
    if "['update'" in installer or "$type === 'update'" in installer:
        fail('package installer must not force-enable plugins during updates')

    if not (ROOT / f'component/admin/sql/updates/mysql/{VERSION}.sql').is_file():
        fail('schema marker missing')
    install = (ROOT / 'component/admin/sql/install.mysql.utf8mb4.sql').read_text()
    if '#__decaromembership_notifications' not in install:
        fail('legacy Membership notifications table unexpectedly removed')
    for sql in (ROOT / 'component/admin/sql').rglob('*.sql'):
        if re.search(r'\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b', sql.read_text(), re.I) and 'uninstall' not in sql.name:
            fail(f'destructive update SQL: {sql}')

    feed = ET.parse(ROOT / 'updates/pkg_decaromembership.xml').getroot().find('update')
    if feed is None or (feed.findtext('version') or '').strip() != VERSION:
        fail('update feed mismatch')
    expected_url = f'https://github.com/xdecaro/membership/releases/download/v{VERSION}/pkg_decaromembership_{VERSION}.zip'
    if (feed.findtext('./downloads/downloadurl') or '').strip() != expected_url:
        fail('update download mismatch')
    sha = (feed.findtext('sha256') or '').strip()
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
        if not path.is_file():
            fail(f'missing {path.name}')

    with zipfile.ZipFile(files[3]) as archive:
        expected = {
            'pkg_decaromembership.xml',
            'script.php',
            'com_decaromembership.zip',
            'plg_xdecaroanalytics_decaromembership.zip',
            'plg_task_decaromembership.zip',
        }
        if set(archive.namelist()) != expected:
            fail('unexpected package contents')

    for path in files[:4]:
        with zipfile.ZipFile(path) as archive:
            if archive.testzip() is not None:
                fail(f'corrupt {path.name}')

    print(f'Membership {VERSION} dist validation OK')


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--dist', action='store_true')
    args = parser.parse_args()
    validate()
    if args.dist:
        validate_dist()


if __name__ == '__main__':
    main()
