#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Looping generation worker for hosts whose cron minimum interval is
 * several minutes (e.g. Namecheap shared: every 5 minutes). One cron run
 * keeps processing until its time budget is nearly up, so the queue is
 * served almost continuously:
 *
 *   php worker/run-loop.php [seconds]   (default 240, clamped 30..270)
 *
 * Safety: an flock prevents overlapping loops (a second instance exits
 * immediately), the loop exits early after 30 idle seconds with an empty
 * queue, and every tick is the same bounded processNext() unit run.php
 * uses, so a killed loop loses nothing: job leases expire and requeue.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use Yatsn\Generation\GenerationJobService;
use Yatsn\Support\Config;
use Yatsn\Support\Migrator;

Config::ensureDirectories();
Migrator::migrate();

$budget = (int) ($argv[1] ?? 240);
$budget = max(30, min(270, $budget));
$deadline = time() + $budget;

$logFile = Config::get('paths.log') . '/worker.log';
$log = static function (array $result) use ($logFile): void {
    $line = sprintf("[%s] %s\n", gmdate('c'), json_encode($result, JSON_UNESCAPED_SLASHES));
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
};

$lockHandle = fopen(Config::get('paths.log') . '/worker-loop.lock', 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    $log(['loop' => false, 'reason' => 'loop_already_running']);
    exit(0);
}

$log(['loop' => true, 'budget_seconds' => $budget]);
$idleSeconds = 0;
while (time() < $deadline) {
    $result = GenerationJobService::processNext();
    $log($result);
    $idle = ($result['processed'] ?? true) === false || ($result['skipped'] ?? false) === true;
    if ($idle) {
        $idleSeconds += 3;
        if ($idleSeconds >= 30) {
            $log(['loop' => false, 'reason' => 'queue_idle']);
            break;
        }
        sleep(3);
    } else {
        $idleSeconds = 0;
    }
}
$log(['loop' => false, 'reason' => 'budget_exhausted_or_idle']);
flock($lockHandle, LOCK_UN);
fclose($lockHandle);
