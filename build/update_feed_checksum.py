#!/usr/bin/env python3
from __future__ import annotations

import hashlib
from pathlib import Path
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
VERSION = (ROOT / 'VERSION').read_text(encoding='utf-8').strip()
PACKAGE = ROOT / f'dist/pkg_decaromembership_{VERSION}.zip'
FEED = ROOT / 'updates/pkg_decaromembership.xml'

sha = hashlib.sha256(PACKAGE.read_bytes()).hexdigest()
tree = ET.parse(FEED)
update = tree.getroot().find('./update')
if update is None:
    raise SystemExit('Missing <update> in Membership update feed')

version_node = update.find('version')
download_node = update.find('./downloads/downloadurl')
sha_node = update.find('sha256')
if version_node is None or download_node is None or sha_node is None:
    raise SystemExit('Membership update feed is missing version/download/sha256 metadata')

version_node.text = VERSION
download_node.text = f'https://github.com/xdecaro/membership/releases/download/v{VERSION}/pkg_decaromembership_{VERSION}.zip'
sha_node.text = sha
ET.indent(tree, space='  ')
tree.write(FEED, encoding='utf-8', xml_declaration=True)
print(sha)
