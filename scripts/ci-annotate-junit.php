<?php

/*
 * Turns failed/errored tests in a JUnit XML report into GitHub Actions error annotations.
 * Usage (CI only): php scripts/ci-annotate-junit.php apps/api/storage/logs/junit.xml
 */

$path = $argv[1] ?? '';

if (! is_file($path)) {
    echo "::error title=Tests did not run::No JUnit report at {$path}. The test runner stopped before running tests (configuration or bootstrap error).\n";
    exit(0);
}

$xml = simplexml_load_file($path);

if ($xml === false) {
    echo "::error title=Unreadable test report::Could not parse {$path}.\n";
    exit(0);
}

$escape = fn (string $text): string => str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $text);
$count = 0;

foreach ($xml->xpath('//testcase[failure or error]') ?: [] as $case) {
    $problem = $case->failure ?? $case->error;
    $message = trim((string) ($problem['message'] ?? '') ?: (string) $problem);
    $title = trim((string) $case['class'].' › '.(string) $case['name']);
    echo '::error title='.$escape(mb_strimwidth($title, 0, 200, '…')).'::'.$escape(mb_strimwidth($message, 0, 2000, '…'))."\n";
    $count++;
}

echo "{$count} failing test(s) annotated.\n";
