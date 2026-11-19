"""RC2 source packaging: deterministic archives, tamper checks, no secrets/research."""
import importlib.util
import io
from pathlib import Path
import subprocess
import tarfile
import tempfile
import unittest

ROOT=Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('release',ROOT/'scripts/deploy/prepare-release.py')
release=importlib.util.module_from_spec(spec);spec.loader.exec_module(release)

class CandidatePackaging(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory(prefix='iclimate-release-');self.addCleanup(self.tmp.cleanup)
        self.root=Path(self.tmp.name)/'source';self.root.mkdir();self.out=Path(self.tmp.name)/'release'
        subprocess.run(['git','init','-q',str(self.root)],check=True)
        (self.root/'app.php').write_text('<?php echo "fixture";\n')
        subprocess.run(['git','-C',str(self.root),'add','app.php'],check=True)
        subprocess.run(['git','-C',str(self.root),'-c','user.name=Verification','-c','user.email=verification@localhost','commit','-qm','Fixture'],check=True)

    def build(self):return release.create(self.root,self.out,'2026.11.18-rc2')

    def test_repeated_archive_bytes_and_fingerprint_are_identical(self):
        first=self.build();data=(self.out/'source.tar.gz').read_bytes()
        second=self.build();self.assertEqual(first,second);self.assertEqual(data,(self.out/'source.tar.gz').read_bytes())
        extracted=Path(self.tmp.name)/'extracted'
        self.assertEqual(release.extract(self.out/'source.tar.gz',self.out/'source.manifest',extracted),1)
        self.assertEqual((extracted/'app.php').read_bytes(),(self.root/'app.php').read_bytes())

    def test_untracked_required_files_included_but_research_and_companions_excluded(self):
        for name in ['deploy/hostinger/tool.sh','resources/views/vendor/mail/header.blade.php','storage/models/experiments/unapproved.pkl','storage/datasets/external/private.csv','docs/deployment/ICLIMATE_RC2_FINAL_READINESS.md']:
            p=self.root/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_text('fixture')
        self.build();entries=release.read_manifest(self.out/'source.manifest')
        self.assertIn('deploy/hostinger/tool.sh',entries);self.assertIn('resources/views/vendor/mail/header.blade.php',entries);self.assertEqual(len(entries),3)

    def test_secret_file_refused(self):
        (self.root/'.env').write_text('APP_KEY=not-a-real-secret')
        with self.assertRaisesRegex(ValueError,'Forbidden'):self.build()

    def test_symlink_refused(self):
        (self.root/'linked.php').symlink_to(self.root/'app.php')
        with self.assertRaisesRegex(ValueError,'symlink'):self.build()

    def test_tampered_source_and_nonempty_destination_refused(self):
        self.build();(self.root/'app.php').write_text('tampered')
        with self.assertRaisesRegex(ValueError,'differs'):release.verify_tree(self.root,self.out/'source.manifest')
        with self.assertRaisesRegex(ValueError,'empty'):release.extract(self.out/'source.tar.gz',self.out/'source.manifest',self.root)

    def test_archive_traversal_and_extra_member_refused(self):
        self.build()
        for name in ['../escape.php','extra.php']:
            with tarfile.open(self.out/'malicious.tar.gz','w:gz') as tar:
                item=tarfile.TarInfo(name);item.size=1;item.mode=0o644;tar.addfile(item,io.BytesIO(b'x'))
            with self.assertRaises(ValueError):release.verify_archive(self.out/'malicious.tar.gz',self.out/'source.manifest')

    def test_archive_missing_file_refused(self):
        self.build()
        with tarfile.open(self.out/'empty.tar.gz','w:gz'):pass
        with self.assertRaisesRegex(ValueError,'Missing'):release.verify_archive(self.out/'empty.tar.gz',self.out/'source.manifest')

if __name__=='__main__':unittest.main()
