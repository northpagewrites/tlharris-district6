/**
 * Validate blueprint.json against the official WordPress Playground schema.
 *
 * The one-click preview reads this file straight from the main branch.
 * Playground rejects a blueprint that does not match its schema before it runs
 * a single step, and the visitor sees an error page instead of the site. That
 * already happened once: a missing `meta.author` field. This catches it first.
 *
 * Usage: npm install && node tools/validate-blueprint.mjs
 *
 * Needs network to fetch the schema. Without it the check is skipped with a
 * warning, because being offline is not a fault in the blueprint.
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import Ajv from 'ajv';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const SCHEMA_URL = 'https://playground.wordpress.net/blueprint-schema.json';

let blueprint;
try {
	blueprint = JSON.parse(fs.readFileSync(path.join(ROOT, 'blueprint.json'), 'utf8'));
} catch (err) {
	console.error(`FAIL: blueprint.json is not readable JSON (${err.message})`);
	process.exit(1);
}

let schema;
try {
	const res = await fetch(SCHEMA_URL);
	if (!res.ok) throw new Error(`HTTP ${res.status}`);
	schema = await res.json();
} catch (err) {
	console.warn(`warn: blueprint check skipped, could not fetch ${SCHEMA_URL} (${err.message})`);
	process.exit(0);
}

// The schema accepts two blueprint formats. This site uses the original one, so
// check against that directly. Otherwise a failure lists errors for both
// formats and buries the real one.
schema.$ref = '#/definitions/BlueprintV1Declaration';

const validate = new Ajv({ allErrors: true, strict: false }).compile(schema);
if (validate(blueprint)) {
	console.log('ok:   blueprint.json matches the Playground schema');
	process.exit(0);
}

console.error('FAIL: blueprint.json does not match the Playground schema:');
for (const e of validate.errors) {
	const missing = e.params && e.params.missingProperty ? ` (${e.params.missingProperty})` : '';
	console.error(`  ${e.instancePath || '/'} ${e.message}${missing}`);
}
process.exit(1);
