<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/ci/check-composer-audit.php <composer-audit.json> <allowlist.json>\n");
    exit(1);
}

$reportPath = $argv[1];
$allowlistPath = $argv[2];

$report = json_decode((string) file_get_contents($reportPath), true);
$allowlist = json_decode((string) file_get_contents($allowlistPath), true);

if (!is_array($report) || !is_array($allowlist)) {
    fwrite(STDERR, "Failed to parse composer audit report or allowlist JSON.\n");
    exit(1);
}

$allowedAdvisories = array_fill_keys($allowlist['allowed_advisory_ids'] ?? [], true);
$allowedAbandoned = array_fill_keys($allowlist['allow_abandoned'] ?? [], true);

$violations = [];

$advisories = $report['advisories'] ?? [];
foreach ($advisories as $package => $entries) {
    if (!is_array($entries)) {
        continue;
    }

    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $id = (string) ($entry['advisoryId'] ?? $entry['cve'] ?? $entry['title'] ?? '');
        if ($id === '' || isset($allowedAdvisories[$id])) {
            continue;
        }

        $violations[] = "Advisory {$id} for package {$package}";
    }
}

$abandoned = $report['abandoned'] ?? [];
foreach ($abandoned as $package => $detail) {
    if (isset($allowedAbandoned[$package])) {
        continue;
    }

    $replacement = '';
    if (is_array($detail) && isset($detail['replacement']) && is_string($detail['replacement'])) {
        $replacement = " (replacement: {$detail['replacement']})";
    }
    $violations[] = "Abandoned package {$package}{$replacement}";
}

if ($violations !== []) {
    fwrite(STDERR, "Composer audit policy check failed:\n");
    foreach ($violations as $violation) {
        fwrite(STDERR, " - {$violation}\n");
    }
    exit(1);
}

fwrite(STDOUT, "Composer audit policy check passed.\n");
exit(0);
