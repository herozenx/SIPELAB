import { copyFileSync, mkdirSync } from 'node:fs';
mkdirSync('public/fonts', { recursive: true });
for (const w of [400, 500, 600, 700, 800]) {
  copyFileSync(`node_modules/@fontsource/plus-jakarta-sans/files/plus-jakarta-sans-latin-${w}-normal.woff2`, `public/fonts/jakarta-${w}.woff2`);
}
console.log('fonts copied');
