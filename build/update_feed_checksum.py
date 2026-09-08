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
node = tree.getroot().find('./update/sha256')
if node is None:
    raise SystemExit('Missing <sha256> in Membership update feed')
node.text = sha
ET.indent(tree, space='  ')
tree.write(FEED, encoding='utf-8', xml_declaration=True)
print(sha)
