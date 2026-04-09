import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const indexPath = resolve(process.cwd(), 'dist', 'index.html');

let html = readFileSync(indexPath, 'utf8');
html = html.replace(/\s+crossorigin(?=[\s>])/g, '');

writeFileSync(indexPath, html, 'utf8');
console.log('postbuild-fix: stripped crossorigin attributes from dist/index.html');
