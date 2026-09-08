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


def fail(message: str) -> None:
    raise SystemExit('ERROR: ' + message)


def version(path: Path) -> str:
    root = ET.parse(path).getroot()
    return (root.findtext('version') or '').strip()


def validate() -> None:
    if VERSION != '1.2.0':
        fail(f'unexpected VERSION {VERSION!r}')

    component = ROOT / 'component/decaromembership.xml'
    package = ROOT / 'package/pkg_decaromembership.xml'
    if version(component) != VERSION or version(package) != VERSION:
        fail('manifest versions are not aligned with VERSION')

    component_root = ET.parse(component).getroot()
    if component_root.find('./files') is not None:
        fail('administrator-only Membership must not reference a missing site source folder')

    for lang in component_root.findall('./administration/languages/language'):
        folder = component_root.find('./administration/languages').get('folder')
        path = ROOT / 'component' / folder / (lang.text or '')
        if not path.is_file():
            fail(f'missing declared language file {path.relative_to(ROOT)}')

    assets = json.loads((ROOT / 'component/media/joomla.asset.json').read_text(encoding='utf-8'))
    if assets.get('version') != VERSION:
        fail('Web Asset registry version mismatch')
    if not any(a.get('name') == 'com_decaromembership.core-bridge' and a.get('type') == 'style' for a in assets.get('assets', [])):
        fail('Core bridge asset is not registered')

    html_view = (ROOT / 'component/admin/src/View/Information/HtmlView.php').read_text(encoding='utf-8')
    for marker in ('xdecaro\\Core\\Asset\\AssetService', 'xdecaro\\Core\\Version', "MINIMUM_CORE_UI_VERSION = '1.3.0'", "setLayout('core')", "useStyle('com_decaromembership.core-bridge')"):
        if marker not in html_view:
            fail(f'Information Core UI integration missing {marker}')

    core_template = (ROOT / 'component/admin/tmpl/information/core.php').read_text(encoding='utf-8')
    for marker in ('xdecaro-scope membership-core-scope', 'Core by xdecaro', 'pkg_xdecarocore', 'xdecaro-card', 'xdecaro-badge'):
        if marker not in core_template:
            fail(f'Core information layout missing {marker}')

    helper = (ROOT / 'component/admin/src/Helper/MembershipHelper.php').read_text(encoding='utf-8')
    if 'com_decarodcl' not in helper or 'com_decarocompetitions' in helper:
        fail('Competitions must use its stable Joomla identifier com_decarodcl')

    model = (ROOT / 'component/admin/src/Model/InformationModel.php').read_text(encoding='utf-8')
    for marker in ('pkg_xdecarocore', 'xdecaro\\Core\\Version', 'api_available', 'ui_available', "'1.3.0'"):
        if marker not in model:
            fail(f'InformationModel missing {marker}')

    adapter = (ROOT / 'component/admin/src/Service/CoreIntegrationService.php').read_text(encoding='utf-8')
    for marker in ("COMPONENT = 'com_decaromembership'", "MINIMUM_CORE_VERSION = '1.3.0'", 'xdecaro\\Core\\Integration\\EntityReference', 'xdecaro\\Core\\Integration\\RelationReference'):
        if marker not in adapter:
            fail(f'Core adapter missing {marker}')

    for text, label in ((html_view, 'Information HtmlView'), (model, 'InformationModel'), (adapter, 'CoreIntegrationService')):
        if re.search(r'Xdecaro\\+Core', text):
            fail(f'legacy Core namespace remains in {label}')

    feed = ET.parse(ROOT / 'updates/pkg_decaromembership.xml').getroot().find('update')
    if feed is None or (feed.findtext('version') or '').strip() != VERSION:
        fail('package update feed version mismatch')
    expected = f'https://github.com/xdecaro/membership/releases/download/v{VERSION}/pkg_decaromembership_{VERSION}.zip'
    if (feed.findtext('./downloads/downloadurl') or '').strip() != expected:
        fail('package update URL mismatch')

    sql_update = ROOT / f'component/admin/sql/updates/mysql/{VERSION}.sql'
    if not sql_update.is_file():
        fail('missing non-destructive schema marker')


def validate_dist() -> None:
    component_zip = ROOT / f'dist/com_decaromembership_{VERSION}.zip'
    package_zip = ROOT / f'dist/pkg_decaromembership_{VERSION}.zip'
    for path in (component_zip, package_zip):
        if not path.is_file():
            fail(f'missing {path.relative_to(ROOT)}')
        with zipfile.ZipFile(path) as archive:
            if archive.testzip() is not None:
                fail(f'corrupt ZIP {path.name}')

    with zipfile.ZipFile(component_zip) as archive:
        names = set(archive.namelist())
        required = {
            'decaromembership.xml',
            'admin/services/provider.php',
            'admin/src/Service/CoreIntegrationService.php',
            'admin/src/View/Information/HtmlView.php',
            'admin/tmpl/information/default.php',
            'admin/tmpl/information/core.php',
            'admin/language/it-IT/com_decaromembership.ini',
            'media/css/admin.css',
            'media/css/core-bridge.css',
            'media/joomla.asset.json',
        }
        missing = required - names
        if missing:
            fail('component ZIP missing: ' + ', '.join(sorted(missing)))

    with zipfile.ZipFile(package_zip) as archive:
        names = set(archive.namelist())
        if names != {'pkg_decaromembership.xml', 'com_decaromembership.zip'}:
            fail(f'unexpected package contents: {sorted(names)}')


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument('--dist', action='store_true')
    args = parser.parse_args()
    validate()
    if args.dist:
        validate_dist()
    print(f'Membership {VERSION} validation OK')


if __name__ == '__main__':
    main()
