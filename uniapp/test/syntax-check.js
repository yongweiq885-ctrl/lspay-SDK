const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const roots = ['lspay', 'example', 'test'];
const files = [];
function walk(dir) {
  fs.readdirSync(dir, { withFileTypes: true }).forEach(entry => {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full);
    else if (entry.name.endsWith('.js')) files.push(full);
  });
}
roots.forEach(root => walk(path.resolve(__dirname, '..', root)));
files.forEach(file => {
  const result = spawnSync(process.execPath, ['--check', file], { encoding: 'utf8' });
  if (result.status !== 0) {
    process.stderr.write(result.stderr);
    process.exit(result.status || 1);
  }
});
console.log(`syntax check passed (${files.length} files)`);
