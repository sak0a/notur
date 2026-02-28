#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const reportPath = process.argv[2];
const allowlistPath = process.argv[3];

if (!reportPath || !allowlistPath) {
    console.error('Usage: node scripts/ci/check-npm-audit.js <audit.json> <allowlist.json>');
    process.exit(1);
}

function loadJson(filePath) {
    const absolute = path.resolve(filePath);
    const raw = fs.readFileSync(absolute, 'utf8');
    return JSON.parse(raw);
}

const report = loadJson(reportPath);
const allowlist = loadJson(allowlistPath);

const allowedPackages = new Set(Array.isArray(allowlist.allowed_packages) ? allowlist.allowed_packages : []);
const vulnerabilities = report.vulnerabilities || {};

const failures = [];
for (const [pkg, details] of Object.entries(vulnerabilities)) {
    if (allowedPackages.has(pkg)) {
        continue;
    }

    const severity = details && typeof details === 'object' ? details.severity || 'unknown' : 'unknown';
    failures.push({ pkg, severity });
}

if (failures.length > 0) {
    console.error(`npm audit contains ${failures.length} non-allowlisted vulnerability package(s):`);
    for (const failure of failures) {
        console.error(` - ${failure.pkg} (severity: ${failure.severity})`);
    }
    process.exit(1);
}

const summary = report.metadata && report.metadata.vulnerabilities
    ? report.metadata.vulnerabilities
    : { total: 0 };
console.log(`npm audit policy check passed (total vulnerabilities reported: ${summary.total ?? 0}).`);
