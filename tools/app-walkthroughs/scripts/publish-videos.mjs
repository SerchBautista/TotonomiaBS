#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const remotionOut = path.join(root, 'remotion/out');
const frontMedia = path.join(root, '../../Front/public/media/walkthroughs');
const frontPosters = path.join(frontMedia, 'posters');
const frontScreens = path.join(frontMedia, 'screens');

for (const dir of [frontMedia, frontPosters, frontScreens]) {
  fs.mkdirSync(dir, { recursive: true });
}

if (!fs.existsSync(remotionOut)) {
  console.error('No rendered videos found. Run: npm run render:dashboard');
  process.exit(1);
}

for (const file of fs.readdirSync(remotionOut)) {
  if (!file.endsWith('.mp4') && !file.endsWith('.webm')) {
    continue;
  }
  fs.copyFileSync(path.join(remotionOut, file), path.join(frontMedia, file));
  console.log(`Published ${file} → Front/public/media/walkthroughs/`);
}

const posterSources = [
  { source: 'dashboard-01-overview.png', target: 'dashboard.jpg' },
  { source: 'overview-01-dashboard.png', target: 'overview.jpg' },
  { source: 'expenses-01-overview.png', target: 'expenses.jpg' },
  { source: 'budgets-01-overview.png', target: 'budgets.jpg' },
  { source: 'fixed-expenses-01-overview.png', target: 'fixed-expenses.jpg' },
  { source: 'workspaces-01-overview.png', target: 'workspaces.jpg' },
];

for (const { source, target } of posterSources) {
  const posterSource = path.join(root, 'assets/screens', source);
  if (fs.existsSync(posterSource)) {
    fs.copyFileSync(posterSource, path.join(frontPosters, target));
    console.log(`Published ${target} poster`);
  }
}

const showcaseScreens = [
  { source: 'overview-01-dashboard.png', target: 'dashboard.png' },
  { source: 'overview-05-expenses.png', target: 'expenses.png' },
  { source: 'overview-07-budgets.png', target: 'budgets.png' },
  { source: 'overview-10-fixed-expenses.png', target: 'fixed-expenses.png' },
  { source: 'overview-13-workspaces.png', target: 'workspaces.png' },
];

for (const { source, target } of showcaseScreens) {
  const screenSource = path.join(root, 'assets/screens', source);
  if (fs.existsSync(screenSource)) {
    fs.copyFileSync(screenSource, path.join(frontScreens, target));
    console.log(`Published ${target} showcase screen`);
  }
}
