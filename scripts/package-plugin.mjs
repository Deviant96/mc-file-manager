#!/usr/bin/env node
/**
 * Build a lean WordPress install zip (no node_modules, src, or docs).
 *
 * Output: dist/mc-file-manager.zip
 */
import { execSync } from 'node:child_process';
import {
  cpSync,
  existsSync,
  mkdirSync,
  rmSync,
  readdirSync,
  statSync,
} from 'node:fs';
import { join, relative, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const distRoot = join(root, 'dist');
const staged = join(distRoot, 'mc-file-manager');
const zipPath = join(distRoot, 'mc-file-manager.zip');

const INCLUDE = [
  'mc-file-manager.php',
  'uninstall.php',
  'readme.txt',
  'includes',
  'assets/build',
];

console.log('Building assets…');
execSync('npm run build', { cwd: root, stdio: 'inherit' });

rmSync(distRoot, { recursive: true, force: true });
mkdirSync(staged, { recursive: true });

for (const item of INCLUDE) {
  const from = join(root, item);
  const to = join(staged, item);
  if (!existsSync(from)) {
    console.warn(`Skipping missing: ${item}`);
    continue;
  }
  mkdirSync(dirname(to), { recursive: true });
  cpSync(from, to, { recursive: true });
}

function walk(dir, files = []) {
  for (const name of readdirSync(dir)) {
    const full = join(dir, name);
    if (statSync(full).isDirectory()) walk(full, files);
    else files.push(full);
  }
  return files;
}

const files = walk(staged);
const bytes = files.reduce((n, f) => n + statSync(f).size, 0);
console.log(`Staged ${files.length} files (${(bytes / 1024 / 1024).toFixed(2)} MB uncompressed)`);

// Prefer system zip; fall back to a tiny pure-JS store zip if unavailable.
try {
  if (existsSync(zipPath)) rmSync(zipPath);
  execSync(`zip -r -q "${zipPath}" mc-file-manager`, { cwd: distRoot, stdio: 'inherit' });
} catch {
  console.log('system zip unavailable, writing store zip via Node…');
  await writeStoreZip(staged, zipPath, 'mc-file-manager');
}

const zipBytes = statSync(zipPath).size;
console.log(`Wrote ${relative(root, zipPath)} (${(zipBytes / 1024 / 1024).toFixed(2)} MB)`);
console.log('Install this zip in WordPress → Plugins → Add New → Upload.');

/**
 * Minimal ZIP (store only) — good enough when `zip` CLI is missing.
 * For smaller uploads, install Info-ZIP / use Git Bash zip.
 */
async function writeStoreZip(folder, outFile, rootName) {
  // Use Node 18+ zlib deflateRaw for proper zip entries.
  const { deflateRawSync } = await import('node:zlib');
  const entries = walk(folder);
  const parts = [];
  const central = [];
  let offset = 0;

  for (const file of entries) {
    const name = rootName + '/' + relative(folder, file).replace(/\\/g, '/');
    const data = await import('node:fs').then((fs) => fs.readFileSync(file));
    const compressed = deflateRawSync(data);
    const crc = crc32(data);
    const local = Buffer.alloc(30 + Buffer.byteLength(name));
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4);
    local.writeUInt16LE(0, 6);
    local.writeUInt16LE(8, 8); // deflate
    local.writeUInt16LE(0, 10);
    local.writeUInt16LE(0, 12);
    local.writeUInt32LE(crc >>> 0, 14);
    local.writeUInt32LE(compressed.length, 18);
    local.writeUInt32LE(data.length, 22);
    local.writeUInt16LE(Buffer.byteLength(name), 26);
    local.writeUInt16LE(0, 28);
    Buffer.from(name).copy(local, 30);

    const cen = Buffer.alloc(46 + Buffer.byteLength(name));
    cen.writeUInt32LE(0x02014b50, 0);
    cen.writeUInt16LE(20, 4);
    cen.writeUInt16LE(20, 6);
    cen.writeUInt16LE(0, 8);
    cen.writeUInt16LE(8, 10);
    cen.writeUInt16LE(0, 12);
    cen.writeUInt16LE(0, 14);
    cen.writeUInt32LE(crc >>> 0, 16);
    cen.writeUInt32LE(compressed.length, 20);
    cen.writeUInt32LE(data.length, 24);
    cen.writeUInt16LE(Buffer.byteLength(name), 28);
    cen.writeUInt16LE(0, 30);
    cen.writeUInt16LE(0, 32);
    cen.writeUInt16LE(0, 34);
    cen.writeUInt16LE(0, 36);
    cen.writeUInt32LE(0, 38);
    cen.writeUInt32LE(offset, 42);
    Buffer.from(name).copy(cen, 46);

    parts.push(local, compressed);
    central.push(cen);
    offset += local.length + compressed.length;
  }

  const centralBuf = Buffer.concat(central);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0);
  end.writeUInt16LE(0, 4);
  end.writeUInt16LE(0, 6);
  end.writeUInt16LE(entries.length, 8);
  end.writeUInt16LE(entries.length, 10);
  end.writeUInt32LE(centralBuf.length, 12);
  end.writeUInt32LE(offset, 16);
  end.writeUInt16LE(0, 20);

  const { writeFileSync } = await import('node:fs');
  writeFileSync(outFile, Buffer.concat([...parts, centralBuf, end]));
}

function crc32(buf) {
  let c = ~0;
  for (let i = 0; i < buf.length; i++) {
    c ^= buf[i];
    for (let k = 0; k < 8; k++) c = (c >>> 1) ^ (0xedb88320 & -(c & 1));
  }
  return ~c;
}
