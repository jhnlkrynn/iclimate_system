// Local mitigation, not an upstream version claim. Keep npm audit warnings visible.
// Repeated extglobs containing wildcards are unnecessary for our build and can
// produce exponentially backtracking regexes. Reject them before regex creation.
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync, readdirSync, existsSync } from 'node:fs';
import { resolve, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(fileURLToPath(new URL('../..', import.meta.url)));
const policy = JSON.parse(readFileSync(new URL('./picomatch-policy.json', import.meta.url), 'utf8'));
const anchor = '    const analysis = analyzeRepeatedExtglob(body, opts);';
const guard = `    // iClimate mitigation: GHSA-fc5q-39xc-vjpf (review before removing).
    if ((token.type === 'plus' || token.type === 'star') && /[*?]/.test(body)) {
      throw new SyntaxError('iClimate build policy rejects wildcard-containing repeated extglobs');
    }
`;
const hash = value => createHash('sha256').update(value).digest('hex');
const checkOnly = process.argv.includes('--check');
let checked = 0;

function inspectPackage(path) {
    const metadata = join(path, 'package.json');
    if (!existsSync(metadata)) return;
    const pkg = JSON.parse(readFileSync(metadata, 'utf8'));
    if (pkg.name === 'picomatch') {
        const parser = join(path, 'lib/parse.js');
        const source = readFileSync(parser, 'utf8');
        const expected = policy.originals[pkg.version];
        if (!expected) throw new Error(`Review unrecognized picomatch version ${pkg.version}`);
        const original = source.replace(guard, '');
        if (hash(original) !== expected || original.split(anchor).length !== 2) {
            throw new Error(`Unexpected picomatch source: ${path}`);
        }
        const patched = original.replace(anchor, guard + anchor);
        if (source !== patched) {
            if (checkOnly) throw new Error(`Missing picomatch mitigation: ${path}; run npm ci with lifecycle scripts enabled`);
            writeFileSync(parser, patched);
        }
        checked++;
    }
    scanModules(join(path, 'node_modules'));
}
function scanModules(path) {
    if (!existsSync(path)) return;
    for (const entry of readdirSync(path, { withFileTypes: true })) {
        if (!entry.isDirectory() || entry.name.startsWith('.')) continue;
        const candidate = join(path, entry.name);
        if (entry.name.startsWith('@')) {
            for (const scoped of readdirSync(candidate, { withFileTypes: true })) {
                if (scoped.isDirectory()) inspectPackage(join(candidate, scoped.name));
            }
        } else inspectPackage(candidate);
    }
}
scanModules(join(root, 'node_modules'));
if (checked === 0) throw new Error('No picomatch installations checked; review dependency tree');
console.log(`picomatch mitigation ${checkOnly ? 'verified' : 'applied'}: ${checked} installations (${policy.advisory})`);
