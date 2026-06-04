#!/usr/bin/env node
/*
  Renders the Haley Yachts CTA end card template to PNGs at exact pixel size.
  Uses the Chrome that ships on this Mac in headless mode via CDP (no npm deps).

  Run from anywhere:
      node /Users/jameschaley/Desktop/Claude/haleyyachts-website/images/video/cta-card/render-cards.js

  Output (next to this script):
      cta-card-1080x1080.png
      cta-card-1080x1350.png

  To re-render after editing the template, just run it again.
*/

const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

const DIR = __dirname;
const TEMPLATE = path.join(DIR, 'cta-card-template.html');
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

const CARDS = [
  { id: 'card-1x1', w: 1080, h: 1080, out: 'cta-card-1080x1080.png' },
  { id: 'card-4x5', w: 1080, h: 1350, out: 'cta-card-1080x1350.png' },
];

// We avoid a websocket dependency by using Chrome's --screenshot mode twice,
// once per card, by generating a temp HTML that contains ONLY that card sized
// to the viewport. This is the most robust no-dependency path.

function buildSingle(cardClass, w, h) {
  const html = fs.readFileSync(TEMPLATE, 'utf8');
  // Extract the matching card block.
  const startMarker = cardClass === 'card-1x1'
    ? '<!-- CARD 1:'
    : '<!-- CARD 2:';
  // Simpler + safe: keep full HTML but hide the other card and pin body padding to 0.
  const otherClass = cardClass === 'card-1x1' ? 'card-4x5' : 'card-1x1';
  const injected = `
    <style>
      body{padding:0 !important;gap:0 !important;background:#fff !important;}
      .${otherClass}{display:none !important;}
    </style>`;
  return html.replace('</head>', injected + '</head>');
}

function renderWithChrome(htmlPath, outPath, w, h) {
  return new Promise((resolve, reject) => {
    const args = [
      '--headless=new',
      '--disable-gpu',
      '--hide-scrollbars',
      '--force-device-scale-factor=1',
      '--allow-file-access-from-files',
      `--window-size=${w},${h}`,
      `--screenshot=${outPath}`,
      `--default-background-color=00000000`,
      '--virtual-time-budget=3000',
      'file://' + htmlPath,
    ];
    const p = spawn(CHROME, args, { stdio: 'inherit' });
    p.on('error', reject);
    p.on('exit', (code) => {
      if (fs.existsSync(outPath)) resolve();
      else reject(new Error('Chrome exited ' + code + ' without producing ' + outPath));
    });
  });
}

(async () => {
  for (const c of CARDS) {
    const single = buildSingle(c.id, c.w, c.h);
    const tmp = path.join(DIR, `.tmp-${c.id}.html`);
    fs.writeFileSync(tmp, single);
    const out = path.join(DIR, c.out);
    process.stdout.write(`Rendering ${c.out} (${c.w}x${c.h}) ... `);
    await renderWithChrome(tmp, out, c.w, c.h);
    fs.unlinkSync(tmp);
    console.log('done');
  }
  console.log('All cards rendered to', DIR);
})().catch((e) => { console.error(e); process.exit(1); });
