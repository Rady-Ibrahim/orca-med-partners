const fs = require('fs');
const base = 'E:\\projects\\orca-med-partners\\resources';
const files = {
  css: base + '\\css\\app.css',
  layout: base + '\\views\\admin\\pages\\layout.blade.php',
  dashboard: base + '\\views\\admin\\dashboard.blade.php',
  js: base + '\\js\\app.js',
};

// deterministic, idempotent token-boundary renames
const renames = [
  [/\.app-frame(?![\w-])/g,  '.app-layout',        'app-frame'],
  [/\.sidebar(?![-\w])/g,    '.app-sidebar',       'sidebar{'],
  [/\.main-content(?![\w-])/g, '.app-main',        'main-content'],
  [/\.topbar(?![\w-])/g,     '.app-header',        'topbar'],
  [/\.topbar-actions(?![\w-])/g, '.app-header-actions', 'topbar-actions'],
  [/\.menu-toggle(?![\w-])/g, '.sidebar-toggle-btn','menu-toggle'],
  [/\.content-wrap(?![\w-])/g,'.page-body',        'content-wrap'],
];

function renameText(s, kind) {
  let changed = [];
  for (const [re, to, label] of renames) {
    const m = s.match(re);
    if (m && m.length) changed.push(`${label}:${m.length}`);
    s = s.replace(re, to);
  }
  return { s, changed };
}

for (const [key, p] of Object.entries(files)) {
  let s;
  try { s = fs.readFileSync(p, 'utf8'); }
  catch (e) { console.log(key, 'READ-FAIL', e.message); continue; }
  const st = new Date();
  const { s: out, changed } = renameText(s, key);
  fs.writeFileSync(p, out);
  console.log(key.padEnd(9), changed.join(', ') || '(no-op)', '| tokens:', JSON.stringify(s.length));
}
console.log('RENAME-DONE');
