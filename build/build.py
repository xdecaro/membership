#!/usr/bin/env python3
from __future__ import annotations
import hashlib, zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]; VERSION=(ROOT/'VERSION').read_text(encoding='utf-8').strip(); DIST=ROOT/'dist'; FIXED=(1980,1,1,0,0,0)
def write_zip(output,files):
 output.parent.mkdir(parents=True,exist_ok=True)
 with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as archive:
  for source,name in sorted(files,key=lambda row:row[1]):
   info=zipfile.ZipInfo(name,FIXED);info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16;archive.writestr(info,source.read_bytes())
def tree(base): return [(p,p.relative_to(base).as_posix()) for p in base.rglob('*') if p.is_file()]
def main():
 DIST.mkdir(exist_ok=True)
 for p in DIST.glob('*'):
  if p.is_file():p.unlink()
 component=DIST/f'com_decaromembership_{VERSION}.zip'; analytics=DIST/f'plg_xdecaroanalytics_decaromembership_{VERSION}.zip'; task=DIST/f'plg_task_decaromembership_{VERSION}.zip'; package=DIST/f'pkg_decaromembership_{VERSION}.zip'
 write_zip(component,tree(ROOT/'component'));write_zip(analytics,tree(ROOT/'plugins/xdecaroanalytics/decaromembership'));write_zip(task,tree(ROOT/'plugins/task/decaromembership'))
 nested=[]
 for source,name in [(component,'com_decaromembership.zip'),(analytics,'plg_xdecaroanalytics_decaromembership.zip'),(task,'plg_task_decaromembership.zip')]:tmp=DIST/name;tmp.write_bytes(source.read_bytes());nested.append((tmp,name))
 write_zip(package,[(ROOT/'package/pkg_decaromembership.xml','pkg_decaromembership.xml'),(ROOT/'package/script.php','script.php')]+nested)
 for p,_ in nested:p.unlink()
 assets=[component,analytics,task,package];(DIST/'SHA256SUMS.txt').write_text(''.join(f'{hashlib.sha256(p.read_bytes()).hexdigest()}  {p.name}\n' for p in assets),encoding='utf-8')
if __name__=='__main__':main()
