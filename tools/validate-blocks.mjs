/**
 * Validate every block in the theme's templates, parts and patterns against
 * the real Gutenberg block definitions.
 *
 * Invalid block markup still renders on the front end, which is why v2 shipped
 * 43 broken blocks unnoticed. It breaks in the Site Editor: each block shows as
 * invalid, and "Attempt Recovery" silently deletes the stray HTML.
 *
 * Usage: npm install && node tools/validate-blocks.mjs
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { createRequire } from 'module';
import { JSDOM } from 'jsdom';

const require = createRequire(import.meta.url);
const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const THEME = path.join(ROOT, 'wp-content/themes/tlharris-public');

// Gutenberg's block library expects a browser.
// jsdom cannot parse the block library's stylesheets and says so loudly on
// stderr. The warnings are about CSS we never render here.
const virtualConsole = new (require('jsdom').VirtualConsole)();
const dom = new JSDOM('<!doctype html><html><body></body></html>', {
	url: 'http://localhost/',
	pretendToBeVisual: true,
	virtualConsole,
});
const w = dom.window;
const globals = [
	'window', 'document', 'HTMLElement', 'Element', 'Node', 'NodeFilter', 'DOMParser',
	'MutationObserver', 'getComputedStyle', 'requestAnimationFrame', 'cancelAnimationFrame',
	'CustomEvent', 'Event', 'KeyboardEvent', 'MouseEvent', 'SVGElement', 'HTMLIFrameElement',
	'DocumentFragment', 'Range', 'Selection',
];
for (const key of globals) {
	try {
		Object.defineProperty(globalThis, key, {
			value: key === 'window' ? w : w[key],
			configurable: true,
			writable: true,
		});
	} catch (e) { /* jsdom does not expose every global */ }
}
try {
	Object.defineProperty(globalThis, 'navigator', { value: w.navigator, configurable: true });
} catch (e) { /* already defined */ }

const noopMedia = () => ({
	matches: false,
	addListener() {}, removeListener() {},
	addEventListener() {}, removeEventListener() {},
});
w.matchMedia = w.matchMedia || noopMedia;
globalThis.matchMedia = w.matchMedia;
globalThis.ResizeObserver = globalThis.ResizeObserver
	|| class { observe() {} unobserve() {} disconnect() {} };
globalThis.IntersectionObserver = globalThis.IntersectionObserver
	|| class { observe() {} unobserve() {} disconnect() {} };
globalThis.CSS = globalThis.CSS || { supports: () => false, escape: (s) => s };

const blocks = require('@wordpress/blocks');
require('@wordpress/block-library').registerCoreBlocks();
// Registration is noisy and the warnings are not ours.
console.error = console.warn = console.info = console.log = () => {};

const out = (...args) => process.stdout.write(args.join(' ') + '\n');

const files = [];
for (const dir of ['templates', 'parts', 'patterns']) {
	const full = path.join(THEME, dir);
	if (!fs.existsSync(full)) continue;
	for (const name of fs.readdirSync(full).sort()) {
		if (name.endsWith('.html') || name.endsWith('.php')) {
			files.push(path.join(full, name));
		}
	}
}

function walk(list, visit) {
	for (const block of list) {
		visit(block);
		if (block.innerBlocks?.length) walk(block.innerBlocks, visit);
	}
}

let totalBlocks = 0;
let invalid = 0;

for (const file of files) {
	// Pattern files carry a PHP header before the block markup.
	const source = fs.readFileSync(file, 'utf8').replace(/^<\?php[\s\S]*?\?>\s*/, '');
	const parsed = blocks.parse(source);
	const problems = [];
	let count = 0;

	walk(parsed, (block) => {
		count += 1;
		if (block.isValid === false || block.name === 'core/freeform' || block.name === 'core/missing') {
			problems.push(block);
		}
	});

	totalBlocks += count;
	invalid += problems.length;
	const rel = path.relative(ROOT, file);

	for (const block of problems) {
		const issue = (block.validationIssues || [])[0];
		let detail = '';

		if (block.name === 'core/freeform') {
			detail = 'raw HTML outside any block';
		} else if (block.name === 'core/missing') {
			detail = 'unbalanced block comment (an opener or closer is missing)';
		} else if (issue) {
			const args = (issue.args || []).slice();
			const template = String(args.shift() || '');
			const values = args.map((a) => (typeof a === 'string' ? a.replace(/\s+/g, ' ').slice(0, 90) : '…'));
			detail = template.replace(/%[so]/g, () => values.shift() ?? '?').replace(/\s+/g, ' ');
		}

		out(`FAIL: ${rel}: ${block.name}: ${detail}`.slice(0, 300));
	}
}

if (invalid > 0) {
	out(`\nBlock validation failed: ${invalid} invalid of ${totalBlocks} blocks in ${files.length} files.`);
	process.exit(1);
}

out(`ok:   block validation (${totalBlocks} blocks in ${files.length} files)`);
