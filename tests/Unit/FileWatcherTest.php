<?php

use IsoDoss\Meridian\Support\FileWatcher;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/meridian-watch-'.uniqid();
    mkdir($this->dir.'/app/Jobs', 0777, true);
    mkdir($this->dir.'/bootstrap/cache', 0777, true);
    file_put_contents($this->dir.'/app/Jobs/SendInvoice.php', '<?php // v1');
    file_put_contents($this->dir.'/.env', 'APP_ENV=local');
    $this->watcher = new FileWatcher(['app', 'bootstrap', '.env', 'missing'], $this->dir);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->dir));
});

it('changes when a watched file changes', function () {
    $before = $this->watcher->fingerprint();

    file_put_contents($this->dir.'/app/Jobs/SendInvoice.php', '<?php // version 2');

    expect($this->watcher->fingerprint())->not->toBe($before);
});

it('changes when a file is added or a watched file outside directories changes', function () {
    $before = $this->watcher->fingerprint();
    file_put_contents($this->dir.'/app/Jobs/NewJob.php', '<?php');
    $afterAdd = $this->watcher->fingerprint();

    file_put_contents($this->dir.'/.env', 'APP_ENV=local'.PHP_EOL.'APP_DEBUG=true');

    expect($afterAdd)->not->toBe($before)
        ->and($this->watcher->fingerprint())->not->toBe($afterAdd);
});

it('ignores cache directories and non PHP files', function () {
    $before = $this->watcher->fingerprint();

    file_put_contents($this->dir.'/bootstrap/cache/packages.php', '<?php return [];');
    file_put_contents($this->dir.'/app/notes.txt', 'todo');

    expect($this->watcher->fingerprint())->toBe($before);
});
