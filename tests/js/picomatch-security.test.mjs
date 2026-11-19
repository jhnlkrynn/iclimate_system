import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { spawnSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdirSync, mkdtempSync, copyFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
const require = createRequire(import.meta.url);
const copies = ['picomatch', 'vite/node_modules/picomatch', 'tinyglobby/node_modules/picomatch'];

for (const module of copies) {
    const entry = require.resolve(resolve('node_modules', module));
    const pm = require(entry);
    test(`${module}: advisory payload is rejected even when recursion guard is disabled`, () => {
        const child = spawnSync(process.execPath, ['-e', `
            const assert = require('node:assert/strict');
            const pm = require(${JSON.stringify(entry)});
            for (const options of [{}, {maxExtglobRecursion:false}]) {
                for (const pattern of ['+(*a)', '+(*)b', '*(*a)', '+(a*|b)', '+(+(a*))']) {
                    assert.throws(() => pm.parse(pattern, options), SyntaxError);
                    try { pm(pattern, options)('a'.repeat(45) + '!'); }
                    catch (error) { assert(error instanceof SyntaxError); }
                }
            }
        `], { timeout: 2000, encoding: 'utf8' });
        assert.equal(child.error, undefined, child.error?.message);
        assert.equal(child.status, 0, child.stderr);
    });
    test(`${module}: normal build globs and literal repeated extglobs retain matching`, () => {
        assert(pm('resources/**/*.js')('resources/js/app.js'));
        assert(pm('*.{js,mjs}')('test.mjs'));
        assert(!pm('*.js')('file.php'));
        assert(pm('+(a|b)')('abba'));
        assert(!pm('+(a|b)')('abc'));
    });
}

test('dependency hardening is reproducible and fails closed on missing or modified code', () => {
    const dir = mkdtempSync(join(tmpdir(), 'iclimate-picomatch-'));
    try {
        mkdirSync(join(dir, 'scripts/security'), {recursive:true});
        mkdirSync(join(dir, 'node_modules/picomatch/lib'), {recursive:true});
        for (const name of ['harden-picomatch.mjs', 'picomatch-policy.json']) copyFileSync(resolve('scripts/security', name), join(dir, 'scripts/security', name));
        const parser = join(dir, 'node_modules/picomatch/lib/parse.js');
        const source = readFileSync(require.resolve('picomatch/lib/parse'), 'utf8');
        const original = source.replace(/    \/\/ iClimate mitigation:[\s\S]+?    const analysis =/, '    const analysis =');
        writeFileSync(parser, original);
        copyFileSync(require.resolve('picomatch/package.json'), join(dir, 'node_modules/picomatch/package.json'));
        const run = (...args) => spawnSync(process.execPath, [join(dir, 'scripts/security/harden-picomatch.mjs'), ...args], {encoding:'utf8'});
        assert.notEqual(run('--check').status, 0);
        assert.equal(run().status, 0);
        assert.equal(run('--check').status, 0);
        writeFileSync(parser, readFileSync(parser, 'utf8') + '\n// unexpected source change\n');
        assert.notEqual(run('--check').status, 0);
    } finally { rmSync(dir, {recursive:true,force:true}); }
});
