#!/usr/bin/env python3
"""Build/verify a proposed source release without staging, committing or tagging.

The final readiness report and release metadata are companion approval artifacts,
not runtime source, and are excluded to avoid self-referential fingerprints.
"""
import argparse
import gzip
import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import re
import subprocess
import tarfile

ROOT = Path(__file__).resolve().parents[2]
EXCLUDED = ('storage/models/experiments/', 'storage/datasets/external/',
            'docs/deployment/releases/', 'storage/app/releases/')
COMPANIONS = {'docs/deployment/ICLIMATE_RC2_FINAL_READINESS.md'}
FORBIDDEN = re.compile(r'(^|/)\.git(/|$)|^(node_modules|vendor|\.venv[^/]*)(/|$)|(^|/)\.env($|\.)|\.(backup|sql\.gz|dump|sqlite|log)$|^(storage/(app|logs|framework)/|bootstrap/cache/)')

def sha(data): return hashlib.sha256(data).hexdigest()

def safe_path(path):
    value = PurePosixPath(path)
    if not path or value.is_absolute() or '..' in value.parts or value.as_posix() != path or any(ord(c) < 32 for c in path) or '\\' in path:
        raise ValueError('Unsafe release path: '+repr(path))
    return path

def selected_files(root):
    result = subprocess.run(['git', '-C', str(root), 'ls-files', '-z', '--cached', '--others', '--exclude-standard'], check=True, capture_output=True)
    selected = []
    for path in sorted(set(os.fsdecode(p) for p in result.stdout.split(b'\0') if p)):
        safe_path(path)
        if path.startswith(EXCLUDED) or path in COMPANIONS: continue
        file = root / path
        if file.is_symlink(): raise ValueError('Source symlink requires explicit review: '+path)
        if not file.exists(): continue  # a staged deletion is absent from the proposed tree
        if path.endswith('/.gitignore'): pass  # safe empty-directory placeholders
        elif path.endswith('.env.example') or path == '.env.example': pass
        elif FORBIDDEN.search(path): raise ValueError('Forbidden release artifact: '+path)
        if not file.is_file(): raise ValueError('Not a regular source file: '+path)
        selected.append(path)
    if not selected: raise ValueError('No source files selected')
    return selected

def read_manifest(path):
    entries = {}
    for line in Path(path).read_text().splitlines():
        if not line or line.startswith('#'): continue
        digest, name = line.split('  ', 1)
        safe_path(name)
        if not re.fullmatch('[0-9a-f]{64}', digest) or name in entries: raise ValueError('Invalid/duplicate manifest entry')
        entries[name] = digest
    if not entries: raise ValueError('Empty manifest')
    return entries

def verify_tree(root, manifest):
    entries = read_manifest(manifest)
    for name, digest in entries.items():
        path = root / name
        if path.is_symlink() or not path.is_file() or sha(path.read_bytes()) != digest:
            raise ValueError('Source differs: '+name)
    return len(entries)

def verify_archive(archive, manifest):
    entries = read_manifest(manifest); seen = set()
    with tarfile.open(archive, 'r:gz') as tar:
        for item in tar:
            safe_path(item.name)
            if not item.isfile() or item.name in seen or item.name not in entries or item.mode not in (0o644, 0o755):
                raise ValueError('Unexpected archive member: '+item.name)
            if sha(tar.extractfile(item).read()) != entries[item.name]: raise ValueError('Archive differs: '+item.name)
            seen.add(item.name)
    if seen != set(entries): raise ValueError('Missing archive members')
    return len(seen)

def extract(archive, manifest, destination):
    verify_archive(archive, manifest)
    if destination.exists() and any(destination.iterdir()): raise ValueError('Extraction destination must be empty')
    destination.mkdir(parents=True, exist_ok=True)
    with tarfile.open(archive, 'r:gz') as tar:
        for item in tar:
            target = destination / item.name
            target.parent.mkdir(parents=True, exist_ok=True)
            with target.open('xb') as stream: stream.write(tar.extractfile(item).read())
            target.chmod(item.mode)
    return verify_tree(destination, manifest)

def create(root, output, version):
    # Metadata is deliberately outside the source tree captured in the manifest.
    output = output.resolve()
    if not re.fullmatch(r'[0-9]{4}\.[0-9]{2}\.[0-9]{2}-rc[0-9]+', version): raise ValueError('Expected YYYY.MM.DD-rcN')
    files = selected_files(root)
    if any(output == (root/f).resolve() or output in (root/f).resolve().parents for f in files):
        raise ValueError('Output must be an excluded metadata/release directory or outside the source tree')
    data = {name: (root/name).read_bytes() for name in files}
    manifest = ''.join(sha(data[name])+'  '+name+'\n' for name in files)
    fingerprint = sha(manifest.encode())
    output.mkdir(parents=True, exist_ok=True)
    mf = output/'source.manifest'; mf.write_text(manifest)
    archive = output/'source.tar.gz'
    with archive.open('wb') as stream, gzip.GzipFile(fileobj=stream, filename='', mode='wb', mtime=0) as compressed:
        with tarfile.open(fileobj=compressed, mode='w', format=tarfile.PAX_FORMAT) as tar:
            for name in files:
                item = tarfile.TarInfo(name); item.size = len(data[name]); item.mode = 0o755 if (root/name).stat().st_mode & 0o111 else 0o644
                item.mtime = 0; item.uid = item.gid = 0; item.uname = item.gname = ''
                tar.addfile(item, io.BytesIO(data[name]))
    verify_archive(archive, mf)
    base = subprocess.check_output(['git','-C',str(root),'rev-parse','HEAD'], text=True).strip()
    metadata = dict(version=version, source_fingerprint=fingerprint, archive_sha256=sha(archive.read_bytes()),
                    files=len(files), base_commit=base, proposed_tag='v'+version, approved_commit=None,
                    exclusions=list(EXCLUDED)+sorted(COMPANIONS))
    (output/'release.json').write_text(json.dumps(metadata, indent=2)+'\n')
    (output/'source.manifest.sha256').write_text(fingerprint+'  source.manifest\n')
    (output/'source.tar.gz.sha256').write_text(metadata['archive_sha256']+'  source.tar.gz\n')
    (output/'approved-paths.txt').write_text('\n'.join(files)+'\n')
    return metadata

def main():
    parser=argparse.ArgumentParser(description=__doc__); sub=parser.add_subparsers(dest='action', required=True)
    build=sub.add_parser('create');build.add_argument('--root',type=Path,default=ROOT);build.add_argument('--output',type=Path,required=True);build.add_argument('--version',required=True)
    for action in ['verify-tree','verify-archive','extract']:
        command=sub.add_parser(action);command.add_argument('--manifest',type=Path,required=True)
        if action=='verify-tree':command.add_argument('--root',type=Path,default=ROOT)
        else:command.add_argument('--archive',type=Path,required=True)
        if action=='extract':command.add_argument('--destination',type=Path,required=True)
    args=parser.parse_args()
    if args.action=='create': print(json.dumps(create(args.root.resolve(), args.output, args.version),indent=2))
    elif args.action=='verify-tree': print('Verified source files:',verify_tree(args.root,args.manifest))
    elif args.action=='verify-archive':print('Verified archive files:',verify_archive(args.archive,args.manifest))
    else:print('Extracted and verified source files:',extract(args.archive,args.manifest,args.destination))
if __name__=='__main__':main()
