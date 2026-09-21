#!/usr/bin/env node
/**
 * Preview tooling. Relates a WordPress Playground preview to a Git commit.
 *
 *   node scripts/preview.mjs url [ref]                 Playground link pinned to one commit (default origin/main)
 *   node scripts/preview.mjs fingerprint [ref]         Fingerprint of the deployable tree at a commit (default HEAD)
 *   node scripts/preview.mjs fingerprint --worktree    The same, from the files on disk
 *   node scripts/preview.mjs verify <stamp.json|-> [ref]   Check a running preview's stamp against a commit
 *
 * See docs/preview-workflow.md. No dependencies beyond Node and git.
 */
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

// Keep in step with tlharris_preview_fingerprint() in preview/fingerprint.php.
const PATHS = [
	'wp-content/themes/tlharris-public',
	'wp-content/plugins/tlharris-core',
	'content',
	'preview',
];
const IGNORED = new Set(['.DS_Store', 'Thumbs.db']);
const PLAYGROUND = 'https://playground.wordpress.net/';

const git = (args) => execFileSync('git', args, { cwd: ROOT, maxBuffer: 256 * 1024 * 1024, stdio: ['ignore', 'pipe', 'pipe'] });
const sha256 = (data) => createHash('sha256').update(data).digest('hex');
const die = (message) => { console.error(`error: ${message}`); process.exit(2); };

function commitOf(ref) {
	try {
		return git(['rev-parse', '--verify', `${ref}^{commit}`]).toString('utf8').trim();
	} catch {
		return die(`"${ref}" is not a commit in this repository. Try "git fetch origin" first.`);
	}
}

function filesAtCommit(commit) {
	const out = git(['ls-tree', '-r', '-z', '--full-tree', commit, '--', ...PATHS]).toString('utf8');
	const files = new Map();
	for (const entry of out.split('\0').filter(Boolean)) {
		const tab = entry.indexOf('\t');
		const [, type, blob] = entry.slice(0, tab).split(' ');
		const name = entry.slice(tab + 1);
		if (type !== 'blob' || IGNORED.has(path.posix.basename(name))) continue;
		files.set(name, sha256(git(['cat-file', 'blob', blob])));
	}
	return files;
}

function filesOnDisk() {
	const files = new Map();
	const walk = (dir) => {
		for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
			const absolute = path.join(dir, entry.name);
			if (entry.isDirectory()) walk(absolute);
			else if (entry.isFile() && !IGNORED.has(entry.name)) {
				files.set(path.relative(ROOT, absolute).split(path.sep).join('/'), sha256(fs.readFileSync(absolute)));
			}
		}
	};
	for (const p of PATHS) {
		if (fs.existsSync(path.join(ROOT, p))) walk(path.join(ROOT, p));
	}
	return files;
}

function fingerprintOf(files) {
	const names = [...files.keys()].sort((a, b) => Buffer.compare(Buffer.from(a), Buffer.from(b)));
	const listing = names.map((name) => `${files.get(name)}  ${name}\n`).join('');
	return { fingerprint: sha256(listing), files: names.length };
}

const readBlueprint = () => JSON.parse(fs.readFileSync(path.join(ROOT, 'blueprint.json'), 'utf8'));

function checkoutStep(blueprint) {
	const found = blueprint.steps.filter((s) => s.filesTree && s.filesTree.resource === 'git:directory');
	if (found.length !== 1) die(`blueprint.json must contain exactly one git:directory checkout, found ${found.length}.`);
	return found[0];
}

const versionMatches = (actual, pin) => actual === pin || String(actual).startsWith(`${pin}.`);

const commands = {
	url([ref = 'origin/main']) {
		const commit = commitOf(ref);
		const remote = git(['branch', '-r', '--contains', commit]).toString('utf8').trim();
		if (!remote) {
			console.error(`warning: ${commit.slice(0, 12)} is on no remote branch. Playground fetches from GitHub, so it cannot load this commit until it is pushed.`);
		}
		const blueprint = readBlueprint();
		const step = checkoutStep(blueprint);
		step.filesTree.ref = commit;
		step.filesTree.refType = 'commit';
		console.error(`pinned to ${commit}`);
		console.log(PLAYGROUND + '#' + encodeURI(JSON.stringify(blueprint)).replace(/\(/g, '%28').replace(/\)/g, '%29').replace(/'/g, '%27'));
	},

	fingerprint(args) {
		if (args[0] === '--worktree') {
			console.log(JSON.stringify({ commit: null, ...fingerprintOf(filesOnDisk()) }));
			return;
		}
		const commit = commitOf(args[0] || 'HEAD');
		console.log(JSON.stringify({ commit, ...fingerprintOf(filesAtCommit(commit)) }));
	},

	verify([stampPath, ref = 'origin/main']) {
		if (!stampPath) die('usage: verify <stamp.json|-> [ref]');
		const stamp = JSON.parse(fs.readFileSync(stampPath === '-' ? 0 : stampPath, 'utf8'));
		const commit = commitOf(ref);
		const want = fingerprintOf(filesAtCommit(commit));
		const pins = readBlueprint().preferredVersions || {};
		const checks = [
			['bootstrap finished cleanly', stamp.status === 'ok' && (stamp.errors || []).length === 0],
			[`fingerprint matches ${commit.slice(0, 12)}`, stamp.fingerprint === want.fingerprint],
			[`file count is ${want.files}`, stamp.files === want.files],
			[`WordPress is ${pins.wp} (got ${stamp.wp})`, versionMatches(stamp.wp, pins.wp)],
			[`PHP is ${pins.php} (got ${stamp.php})`, versionMatches(stamp.php, pins.php)],
		];
		let failed = false;
		for (const [label, ok] of checks) {
			console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}`);
			failed ||= !ok;
		}
		console.log(failed ? `\nThe preview does NOT represent ${commit}.` : `\nThe preview represents ${commit}.`);
		process.exit(failed ? 1 : 0);
	},
};

const [name, ...rest] = process.argv.slice(2);
if (!commands[name]) die('usage: preview.mjs url [ref] | fingerprint [ref|--worktree] | verify <stamp.json|-> [ref]');
commands[name](rest);
