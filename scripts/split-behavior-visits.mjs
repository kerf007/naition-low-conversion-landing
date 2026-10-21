import { readFile, mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

const [, , source, destination] = process.argv;
if (!source || !destination) {
    console.error('Usage: node scripts/split-behavior-visits.mjs <visits.json> <output-dir>');
    process.exit(1);
}

const report = JSON.parse(await readFile(source, 'utf8'));
if (!Array.isArray(report.visits)) {
    throw new Error('Expected a visits array in the exported report.');
}

await mkdir(destination, { recursive: true });
let converted = 0;
for (const visit of report.visits) {
    if (typeof visit.visitId !== 'string' || !/^[0-9a-f-]{36}$/.test(visit.visitId)) {
        throw new Error('Invalid visit ID in the exported report.');
    }

    converted += Number(visit.converted === true);
    await writeFile(join(destination, `${visit.visitId}.json`), `${JSON.stringify(visit, null, 2)}\n`);
}

await writeFile(join(destination, 'manifest.json'), `${JSON.stringify({
    generatedAt: report.generated_at,
    visits: report.visits.length,
    converted,
}, null, 2)}\n`);
console.log(`Saved ${report.visits.length} visits (${converted} converted) to ${destination}`);
