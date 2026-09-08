#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = (ROOT / 'VERSION').read_text(encoding='utf-8').strip()
DIST = ROOT / 'dist'
FIXED_TIME = (1980, 1, 1, 0, 0, 0)


def write_zip(output: Path, files: list[tuple[Path, str]]) -> None:
    output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for source, name in sorted(files, key=lambda row: row[1]):
            info = zipfile.ZipInfo(name, FIXED_TIME)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            archive.writestr(info, source.read_bytes())


def tree_files(base: Path) -> list[tuple[Path, str]]:
    return [(path, path.relative_to(base).as_posix()) for path in base.rglob('*') if path.is_file()]


def main() -> None:
    if not VERSION:
        raise SystemExit('VERSION is empty')

    DIST.mkdir(exist_ok=True)
    for path in DIST.glob('*'):
        if path.is_file():
            path.unlink()

    component_zip = DIST / f'com_decaromembership_{VERSION}.zip'
    package_zip = DIST / f'pkg_decaromembership_{VERSION}.zip'

    write_zip(component_zip, tree_files(ROOT / 'component'))

    package_manifest = ROOT / 'package/pkg_decaromembership.xml'
    nested_component = DIST / 'com_decaromembership.zip'
    nested_component.write_bytes(component_zip.read_bytes())
    write_zip(package_zip, [
        (package_manifest, 'pkg_decaromembership.xml'),
        (nested_component, 'com_decaromembership.zip'),
    ])
    nested_component.unlink()

    sums = []
    for path in (component_zip, package_zip):
        sums.append(f"{hashlib.sha256(path.read_bytes()).hexdigest()}  {path.name}")
    (DIST / 'SHA256SUMS.txt').write_text('\n'.join(sums) + '\n', encoding='utf-8')


if __name__ == '__main__':
    main()
