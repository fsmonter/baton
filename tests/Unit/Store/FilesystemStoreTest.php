<?php

use Baton\Store\FilesystemStore;

beforeEach(function () {
    $this->storeDir = sys_get_temp_dir().'/baton-test-store-'.uniqid();
    $this->store = new FilesystemStore($this->storeDir);
});

afterEach(function () {
    // Clean up
    if (is_dir($this->storeDir)) {
        array_map('unlink', glob($this->storeDir.'/*.json'));
        rmdir($this->storeDir);
    }
});

test('save and get process data', function () {
    $data = ['id' => 'test-1', 'driver' => 'null', 'handle' => ['pid' => 123]];

    $this->store->save('test-1', $data);

    expect($this->store->get('test-1'))->toBe($data);
});

test('get returns null for missing id', function () {
    expect($this->store->get('nonexistent'))->toBeNull();
});

test('exists returns true for saved data', function () {
    $this->store->save('test-1', ['id' => 'test-1']);

    expect($this->store->exists('test-1'))->toBeTrue();
});

test('exists returns false for missing data', function () {
    expect($this->store->exists('nonexistent'))->toBeFalse();
});

test('all returns all saved entries', function () {
    $this->store->save('a', ['id' => 'a', 'driver' => 'null']);
    $this->store->save('b', ['id' => 'b', 'driver' => 'null']);

    $all = $this->store->all();

    expect($all)->toHaveCount(2)
        ->and($all['a']['id'])->toBe('a')
        ->and($all['b']['id'])->toBe('b');
});

test('all returns empty array when no entries', function () {
    expect($this->store->all())->toBe([]);
});

test('remove deletes entry', function () {
    $this->store->save('test-1', ['id' => 'test-1']);
    $this->store->remove('test-1');

    expect($this->store->exists('test-1'))->toBeFalse()
        ->and($this->store->get('test-1'))->toBeNull();
});

test('remove is safe for missing id', function () {
    // Should not throw
    $this->store->remove('nonexistent');

    expect(true)->toBeTrue();
});

test('save overwrites existing data', function () {
    $this->store->save('test-1', ['id' => 'test-1', 'version' => 1]);
    $this->store->save('test-1', ['id' => 'test-1', 'version' => 2]);

    expect($this->store->get('test-1')['version'])->toBe(2);
});

test('creates directory if it does not exist', function () {
    $dir = sys_get_temp_dir().'/baton-nested-'.uniqid().'/deep';
    $store = new FilesystemStore($dir);

    $store->save('test-1', ['id' => 'test-1']);

    expect($store->exists('test-1'))->toBeTrue();

    // Clean up
    unlink($dir.'/test-1.json');
    rmdir($dir);
    rmdir(dirname($dir));
});
