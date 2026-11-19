"""Safe deployment regressions: disposable checkouts and synthetic SQL only."""
import os
from pathlib import Path
import stat
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
ARTIFACTS = Path(os.environ.get('ICLIMATE_TEST_ARTIFACTS', str(ROOT / 'storage/app/backups/preflight-2026-11-18')))

class DeploymentSafety(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='iclimate-deploy-regression-')
        self.addCleanup(self.tmp.cleanup)
        self.base = Path(self.tmp.name)
        self.app = self.base / 'app'
        self.env = os.environ | dict(BUNDLE=str(ARTIFACTS/'iclimate-v2026.11.18-rc1.bundle'),
            MANIFEST=str(ARTIFACTS/'release-2026.11.18-rc1.manifest'), APP=str(self.app), VERIFY_ONLY='1',
            TAG='v2026.11.18-rc1', EXPECTED_COMMIT='d43cd1aa1b3915b2b2e2928e21b207b3346f1170',
            EXPECTED_FINGERPRINT='bd8f2c9432d62b6191a4845c8085414fa8cfeef09eb42a60e9bd4b5a54b1266f')
        # GNU utilities match the target Linux semantics when this runs on macOS.
        gnu = '/opt/homebrew/opt/coreutils/libexec/gnubin'
        if Path(gnu).exists(): self.env['PATH'] = gnu + ':' + self.env['PATH']

    def deploy(self):
        return subprocess.run(['bash', str(ROOT/'deploy/hostinger/deploy-release.sh')], cwd=self.base,
            env=self.env, capture_output=True, text=True)

    def git(self, *args):
        return subprocess.run(['git','-C',str(self.app),*args], check=True, capture_output=True)

    def checkout(self):
        result=self.deploy()
        self.assertEqual(result.returncode,0,result.stdout+result.stderr)

    def test_exact_bundle_and_manifest_install(self):
        self.checkout()
        self.assertEqual(self.git('rev-parse','HEAD').stdout.decode().strip(),
            'd43cd1aa1b3915b2b2e2928e21b207b3346f1170')

    def test_untracked_operator_file_preserved(self):
        self.checkout(); f=self.app/'operator-notes.txt'; f.write_text('preserve me')
        self.assertNotEqual(self.deploy().returncode,0)
        self.assertEqual(f.read_text(),'preserve me')

    def test_tracked_edit_preserved(self):
        self.checkout(); f=self.app/'README.md'; f.write_text('operator modification')
        self.assertNotEqual(self.deploy().returncode,0)
        self.assertEqual(f.read_text(),'operator modification')

    def test_untracked_collision_preserved(self):
        self.checkout();self.git('checkout','--orphan','disposable')
        self.git('rm','-r','--cached','.')
        self.git('-c','user.name=Verification','-c','user.email=verification@localhost','commit','--allow-empty','-m','disposable')
        f=self.app/'README.md';f.write_text('unexpected operator content')
        self.assertNotEqual(self.deploy().returncode,0)
        self.assertEqual(f.read_text(),'unexpected operator content')

    def test_fingerprint_mismatch_refused_before_destination_created(self):
        self.env['EXPECTED_FINGERPRINT']='0'*64
        self.assertNotEqual(self.deploy().returncode,0)
        self.assertFalse(self.app.exists())

    def test_missing_bundle_tag_refused(self):
        self.env['TAG']='nonexistent-release'
        self.assertNotEqual(self.deploy().returncode,0)
        self.assertFalse(self.app.exists())

    def production_env(self):
        self.checkout(); envfile=self.base/'production.env'
        envfile.write_text('APP_ENV=production\nAPP_DEBUG=false\nDB_CONNECTION=mysql\nICLIMATE_YIELD_ACCURACY_VERIFIED=false\nICLIMATE_PASSWORD_LOCKDOWN=true\nICLIMATE_YIELD_GATE=withhold\nSESSION_SECURE_COOKIE=true\nBROADCAST_CONNECTION=reverb\nREVERB_APP_KEY=disposable-public-key\nVITE_REVERB_APP_KEY=disposable-public-key\nAPP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=\n')
        envfile.chmod(0o640);self.env.update(VERIFY_ONLY='0',ENV_FILE=str(envfile))
        return envfile

    def test_production_template_resolves_private_broadcast_transport(self):
        values={}
        for line in (ROOT/'deploy/hostinger/iclimate.env.example').read_text().splitlines():
            if line and not line.startswith('#') and '=' in line:
                key,value=line.split('=',1);values[key]=value
        self.assertIn('VITE_REVERB_APP_KEY',values)
        env=self.env | values | dict(APP_ENV='testing',APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
             DB_CONNECTION='sqlite',DB_DATABASE=':memory:',DB_URL='',CACHE_STORE='array',LOG_CHANNEL='null')
        code='require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo json_encode([config("broadcasting.default"),config("broadcasting.connections.reverb.options.host"),config("broadcasting.connections.reverb.options.port"),config("broadcasting.connections.reverb.options.scheme")]);'
        result=subprocess.run(['php','-r',code],cwd=ROOT,env=env,capture_output=True,text=True)
        self.assertEqual(result.returncode,0,result.stderr)
        import json
        self.assertEqual(json.loads(result.stdout),['reverb','127.0.0.1',8080,'http'])
        self.assertEqual(values['VITE_REVERB_PORT'],'443')
        self.assertEqual(values['VITE_REVERB_SCHEME'],'https')

    def test_missing_frontend_reverb_key_refused(self):
        envfile=self.production_env()
        envfile.write_text(envfile.read_text().replace('VITE_REVERB_APP_KEY=disposable-public-key', 'VITE_REVERB_APP_KEY='))
        result=self.deploy();self.assertNotEqual(result.returncode,0)
        self.assertIn('VITE_REVERB_APP_KEY must match',result.stdout)
        self.assertFalse((self.app/'.env').exists())

    def test_duplicate_setting_refused(self):
        envfile=self.production_env()
        envfile.write_text('ICLIMATE_YIELD_GATE=off\n'+envfile.read_text())   # Laravel would read this first value
        result=self.deploy()
        self.assertNotEqual(result.returncode,0); self.assertIn('duplicate settings',result.stderr+result.stdout)
        self.assertFalse((self.app/'.env').exists())

    def refused_with(self, old, new, message):
        envfile=self.production_env()
        envfile.write_text(envfile.read_text().replace(old,new))
        result=self.deploy()
        self.assertNotEqual(result.returncode,0); self.assertIn(message,result.stderr+result.stdout)
        self.assertFalse((self.app/'.env').exists())

    def test_enabled_accuracy_flag_refused(self):
        self.refused_with('ICLIMATE_YIELD_ACCURACY_VERIFIED=false','ICLIMATE_YIELD_ACCURACY_VERIFIED=true','ICLIMATE_YIELD_ACCURACY_VERIFIED')

    def test_mariadb_driver_refused(self):
        self.refused_with('DB_CONNECTION=mysql','DB_CONNECTION=mariadb','DB_CONNECTION must be mysql')

    def test_invalid_production_key_refused(self):
        envfile=self.production_env()
        envfile.write_text(envfile.read_text().replace('base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', 'not-a-valid-key'))
        result=self.deploy();self.assertNotEqual(result.returncode,0)
        self.assertIn('APP_KEY must be',result.stdout)
        self.assertFalse((self.app/'.env').exists())

    def test_existing_env_never_overwritten(self):
        self.production_env();f=self.app/'.env';f.write_text('operator secret file')
        result=self.deploy();self.assertNotEqual(result.returncode,0)
        self.assertIn('existing .env',result.stdout)
        self.assertEqual(f.read_text(),'operator secret file')

    def test_publicly_readable_env_refused(self):
        self.production_env().chmod(0o644)
        result=self.deploy();self.assertNotEqual(result.returncode,0)
        self.assertIn('permissions are unsafe',result.stdout)
        self.assertFalse((self.app/'.env').exists())

    def provision_preflight(self, operator='deploy', address='127.0.0.1', active=False):
        bindir=self.base/'bin';bindir.mkdir(exist_ok=True)
        commands={'id':'echo 0', 'grep':'exit 0', 'systemctl': 'exit 0' if active else 'exit 1',
                  'apt-get': 'touch "'+str(self.base/'mutation')+'"; exit 99'}
        for name, body in commands.items():
            f=bindir/name;f.write_text('#!/bin/bash\n'+body+'\n');f.chmod(0o700)
        env=self.env | dict(PATH=str(bindir)+':'+self.env['PATH'],OPERATOR=operator,
                            ADMIN_IP=address,OPERATOR_PUBKEY='ssh-ed25519 disposable')
        result=subprocess.run(['bash',str(ROOT/'deploy/hostinger/provision-ubuntu-24.04.sh')],env=env,capture_output=True,text=True)
        self.assertNotEqual(result.returncode,0)
        self.assertFalse((self.base/'mutation').exists())
        return result.stdout

    def test_provision_rejects_root_operator_before_mutation(self):
        self.assertIn('invalid non-root operator', self.provision_preflight(operator='root'))

    def test_provision_rejects_config_injection_before_mutation(self):
        self.assertIn('single IP address', self.provision_preflight(address='127.0.0.1; allow all;'))

    def test_provision_refuses_active_service_before_mutation(self):
        self.assertIn('already active', self.provision_preflight(active=True))

    def test_backup_checksum_survives_rename_and_transfer(self):
        dump=self.base/'fake-dump'
        dump.write_text('#!/usr/bin/env bash\nfor arg; do case "$arg" in --result-file=*) printf "%s\\n" "-- SQL fixture" "-- Dump completed" > "${arg#*=}";; esac; done\n')
        dump.chmod(0o700); folder=self.base/'backups'
        env=self.env | dict(DB_DATABASE='disposable',BACKUP_DB_HOST='127.0.0.1',BACKUP_DB_PORT='19336',
            BACKUP_DB_USERNAME='fixture',BACKUP_DB_PASSWORD='fixture',BACKUP_DIRECTORY=str(folder),MYSQLDUMP_BINARY=str(dump))
        result=subprocess.run(['bash',str(ROOT/'deploy/hostinger/iclimate-backup.sh')],env=env,capture_output=True,text=True)
        self.assertEqual(result.returncode,0,result.stderr)
        sql=next(folder.glob('*.sql')); checksum=Path(str(sql)+'.sha256')
        self.assertEqual(stat.S_IMODE(sql.stat().st_mode),0o600)
        transfer=self.base/'transfer';transfer.mkdir()
        sql.rename(transfer/sql.name);checksum.rename(transfer/checksum.name)
        result=subprocess.run(['sha256sum','-c',checksum.name],cwd=transfer,env=env,capture_output=True,text=True)
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertFalse(list(folder.glob('*.partial*')))

if __name__ == '__main__': unittest.main()
