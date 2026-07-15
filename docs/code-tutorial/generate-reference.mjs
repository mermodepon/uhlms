import fs from 'node:fs';
import path from 'node:path';

const tutorialDir = path.dirname(new URL(import.meta.url).pathname.replace(/^\/(?:([A-Za-z]:))/, '$1'));
const projectRoot = path.resolve(tutorialDir, '../..');
const allowedExtensions = new Set(['.php', '.js', '.css', '.json', '.xml', '.sh', '.bat', '.ps1']);
const allowedNames = new Set(['artisan', '.editorconfig', '.gitattributes', '.gitignore']);
const excludedDirectories = [
  '.git', 'vendor', 'node_modules', 'storage', 'bootstrap/cache',
  'public/js/filament', 'public/css/filament', 'public/js/saade',
  'docs/code-tutorial',
];
const excludedFiles = new Set(['composer.lock', 'package-lock.json', '.env', '.env.example', '.env.testing.example']);

function walk(directory, relative = '') {
  const results = [];
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const relativePath = path.posix.join(relative.replaceAll('\\', '/'), entry.name);
    if (excludedDirectories.some(item => relativePath === item || relativePath.startsWith(`${item}/`))) continue;
    if (excludedFiles.has(relativePath) || excludedFiles.has(entry.name)) continue;
    const absolutePath = path.join(directory, entry.name);
    if (entry.isDirectory()) results.push(...walk(absolutePath, relativePath));
    else if (allowedNames.has(entry.name) || allowedExtensions.has(path.extname(entry.name))) results.push(relativePath);
  }
  return results;
}

function category(file) {
  if (file === 'public/index.php' || file === 'artisan' || file.startsWith('bootstrap/')) return '01 · Application entry & boot';
  if (file.startsWith('routes/')) return '02 · Routes & entry points';
  if (file.startsWith('app/Models/')) return 'Domain · Models';
  if (file.startsWith('app/Http/')) return 'HTTP · Controllers';
  if (file.startsWith('app/Services/')) return 'Domain · Services';
  if (file.startsWith('app/Filament/Resources/')) return 'Staff UI · Resources';
  if (file.startsWith('app/Filament/')) return 'Staff UI · Pages & Widgets';
  if (file.startsWith('app/Policies/')) return 'Security · Policies';
  if (file.startsWith('app/Observers/')) return 'Domain · Observers';
  if (file.startsWith('app/Console/')) return 'Operations · Commands';
  if (file.startsWith('app/Jobs/')) return 'Operations · Jobs';
  if (file.startsWith('app/Mail/') || file.startsWith('app/Notifications/')) return 'Communication';
  if (file.startsWith('app/')) return 'Application support';
  if (file.startsWith('database/migrations/')) return 'Database · Migrations';
  if (file.startsWith('database/')) return 'Database · Seed data';
  if (file.startsWith('resources/views/')) return 'Frontend · Blade views';
  if (file.startsWith('resources/js/')) return 'Frontend · JavaScript';
  if (file.startsWith('resources/css/')) return 'Frontend · Styles';
  if (file.startsWith('tests/Feature/')) return 'Tests · Feature';
  if (file.startsWith('tests/')) return 'Tests · Unit & support';
  if (file.startsWith('config/')) return 'Configuration';
  if (file.startsWith('public/')) return 'Public authored assets';
  if (file.startsWith('scripts/') || /\.(?:bat|sh|ps1)$/.test(file)) return 'Operations · Scripts';
  return 'Project foundations';
}

function purpose(file) {
  const name = path.basename(file).replace(/\.blade\.php$|\.[^.]+$/g, '').replace(/([a-z])([A-Z])/g, '$1 $2');
  if (file.startsWith('app/Models/')) return `Represents ${name} records and defines their database fields, type conversions, relationships, queries, and domain helpers.`;
  if (file.startsWith('app/Http/Controllers/')) return `Coordinates HTTP requests for ${name}: validates input, invokes domain code, and selects the response or view.`;
  if (file.startsWith('app/Services/')) return `Centralizes the ${name} business workflow so the same rules can be reused safely by controllers, admin actions, and jobs.`;
  if (file.includes('/Policies/')) return `Authorizes staff operations involving ${name}; each method answers whether the current user may perform one action.`;
  if (file.includes('/Observers/')) return `Reacts automatically when ${name.replace(' Observer', '')} records are created, changed, or deleted.`;
  if (file.startsWith('app/Filament/Resources/')) return `Builds part of the Filament staff interface for ${name}, including forms, tables, actions, relation management, or a custom workflow.`;
  if (file.startsWith('app/Filament/')) return `Implements the ${name} area of the staff panel.`;
  if (file.startsWith('database/migrations/')) return `Applies one versioned database schema change and defines how that change can be reversed.`;
  if (file.startsWith('database/seeders/')) return `Creates intentional starter or demonstration data for ${name}.`;
  if (file.startsWith('resources/views/emails/')) return `Renders the ${name} email body using data supplied by its mail class.`;
  if (file.endsWith('.blade.php')) return `Renders the ${name} interface with Blade, combining HTML with server-provided PHP data and directives.`;
  if (file.startsWith('resources/js/')) return `Implements browser-side behavior for ${name}, including DOM interaction, state, requests, or the virtual-tour interface.`;
  if (file.startsWith('tests/Feature/')) return `Specifies the externally visible ${name} behavior across routes, middleware, database records, and responses.`;
  if (file.startsWith('tests/')) return `Specifies the isolated ${name} behavior and protects it from regressions.`;
  if (file.startsWith('config/')) return `Returns the ${name} configuration consumed by Laravel and environment-specific runtime settings.`;
  if (file.startsWith('routes/')) return `Declares the ${name} entry points that connect requests or commands to application code.`;
  if (file.endsWith('.css')) return `Defines authored styling rules for ${name}.`;
  if (/\.(?:bat|sh|ps1)$/.test(file)) return `Automates the ${name} operational workflow for local or hosted environments.`;
  return `Defines the project-level ${name} setup or runtime behavior.`;
}

function language(file) {
  if (file.endsWith('.blade.php')) return 'Blade / PHP / HTML';
  const ext = path.extname(file).slice(1);
  return ({ php:'PHP', js:'JavaScript', css:'CSS', json:'JSON', xml:'XML', sh:'Shell', bat:'Windows batch', ps1:'PowerShell' })[ext] || 'Configuration';
}

function symbols(source, file) {
  const found = [];
  const patterns = file.endsWith('.js')
    ? [/^(?:export\s+)?(?:async\s+)?function\s+([\w$]+)/, /^(?:export\s+)?class\s+(\w+)/, /^(?:const|let|var)\s+([\w$]+)\s*=\s*(?:async\s*)?\(/]
    : [/^(?:final\s+|abstract\s+)?class\s+(\w+)/, /^\s*(?:public|protected|private)?\s*(?:static\s+)?function\s+(\w+)/];
  source.split(/\r?\n/).forEach((line, index) => {
    for (const pattern of patterns) {
      const match = line.match(pattern);
      if (match) { found.push({ name: match[1], line: index + 1 }); break; }
    }
  });
  return found;
}

const files = walk(projectRoot).sort((a, b) => a.localeCompare(b)).map(file => {
  const source = fs.readFileSync(path.join(projectRoot, ...file.split('/')), 'utf8').replaceAll('\r\n', '\n');
  return { path:file, category:category(file), purpose:purpose(file), language:language(file), lines:source.split('\n').length, symbols:symbols(source, file), source };
});

// Build a lightweight first-party dependency graph for the tutorial. This is
// intentionally limited to relationships visible in source code: PHP imports,
// Blade view composition, view() calls, and relative JavaScript imports.
const classToFile = new Map();
const fileByPath = new Map(files.map(file => [file.path, file]));
for (const file of files) {
  const namespace = file.source.match(/^namespace\s+([^;]+);/m)?.[1];
  const className = file.source.match(/^(?:final\s+|abstract\s+)?class\s+(\w+)/m)?.[1];
  if (namespace && className) classToFile.set(`${namespace}\\${className}`, file.path);
  file.uses = [];
  file.referencedBy = [];
}

for (const file of files) {
  const dependencies = new Set();
  for (const match of file.source.matchAll(/^use\s+(App\\[^;{]+);/gm)) {
    const target = classToFile.get(match[1].trim());
    if (target && target !== file.path) dependencies.add(target);
  }
  for (const match of file.source.matchAll(/(?:view|@extends|@include|@component)\s*\(\s*['"]([^'"]+)['"]/g)) {
    const target = `resources/views/${match[1].replaceAll('.', '/')}.blade.php`;
    if (fileByPath.has(target) && target !== file.path) dependencies.add(target);
  }
  if (file.path.endsWith('.js')) {
    for (const match of file.source.matchAll(/(?:from\s+|import\s*)['"](\.[^'"]+)['"]/g)) {
      const base = path.posix.dirname(file.path);
      let target = path.posix.normalize(path.posix.join(base, match[1]));
      if (!path.posix.extname(target)) target += '.js';
      if (fileByPath.has(target) && target !== file.path) dependencies.add(target);
    }
  }
  file.uses = [...dependencies].sort();
}

for (const file of files) {
  for (const target of file.uses) fileByPath.get(target).referencedBy.push(file.path);
}
for (const file of files) file.referencedBy.sort();

const categoryOrder = [
  '01 · Application entry & boot', '02 · Routes & entry points', 'Configuration',
  'HTTP · Controllers', 'Domain · Models', 'Domain · Services', 'Domain · Observers',
  'Security · Policies', 'Database · Migrations', 'Database · Seed data',
  'Staff UI · Resources', 'Staff UI · Pages & Widgets', 'Frontend · Blade views',
  'Frontend · JavaScript', 'Frontend · Styles', 'Communication', 'Application support',
  'Public authored assets', 'Operations · Jobs', 'Operations · Commands', 'Operations · Scripts',
  'Tests · Unit & support', 'Tests · Feature', 'Project foundations',
];
files.sort((a, b) => {
  const categoryDifference = categoryOrder.indexOf(a.category) - categoryOrder.indexOf(b.category);
  return categoryDifference || a.path.localeCompare(b.path);
});

const output = `/* Generated by generate-reference.mjs. Re-run after source changes. */\nwindow.UHLMS_SOURCE_REFERENCE = ${JSON.stringify(files)};\n`;
fs.writeFileSync(path.join(tutorialDir, 'source-reference.js'), output, 'utf8');
console.log(`Generated reference for ${files.length} files and ${files.reduce((sum, file) => sum + file.lines, 0).toLocaleString()} lines.`);
