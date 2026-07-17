#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const sourceScreens = path.join(root, 'assets/screens');
const targetScreens = path.join(root, 'remotion/public/screens');
const targetPublic = path.join(root, 'remotion/public');

fs.mkdirSync(targetScreens, { recursive: true });

for (const entry of fs.readdirSync(sourceScreens)) {
  if (!entry.endsWith('.png')) {
    continue;
  }
  fs.copyFileSync(path.join(sourceScreens, entry), path.join(targetScreens, entry));
}

const manifestCandidates = fs
  .readdirSync(root)
  .filter((name) => name.startsWith('assets/screens-manifest.') && name.endsWith('.json'));

for (const manifestFile of manifestCandidates) {
  const slug = manifestFile.replace('assets/screens-manifest.', '').replace('.json', '');
  fs.copyFileSync(
    path.join(root, manifestFile),
    path.join(root, 'remotion', `screens.${slug}.json`),
  );
}

console.log(`Synced PNG assets to ${targetScreens}`);
