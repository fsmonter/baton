<?php

use Baton\Output\ErrorEvent;
use Baton\Output\MessageEvent;
use Baton\Output\ProgressEvent;
use Baton\Output\ResultEvent;
use Baton\Output\ToolResultEvent;
use Baton\Output\ToolUseEvent;

test('message event stores content and role', function () {
    $event = new MessageEvent(content: 'Hello', role: 'assistant');

    expect($event->content)->toBe('Hello')
        ->and($event->role)->toBe('assistant')
        ->and($event->timestamp)->toBeInstanceOf(\Carbon\Carbon::class);
});

test('tool use event stores tool info', function () {
    $event = new ToolUseEvent(tool: 'Read', input: ['path' => 'f.txt'], toolId: 'abc');

    expect($event->tool)->toBe('Read')
        ->and($event->input)->toBe(['path' => 'f.txt'])
        ->and($event->toolId)->toBe('abc');
});

test('tool result event stores result', function () {
    $event = new ToolResultEvent(toolUseId: 'abc', toolName: 'Read', content: 'contents', isError: false);

    expect($event->toolUseId)->toBe('abc')
        ->and($event->toolName)->toBe('Read')
        ->and($event->isError)->toBeFalse();
});

test('progress event stores percent', function () {
    $event = new ProgressEvent(percent: 50, activity: 'Working...');

    expect($event->percent)->toBe(50)
        ->and($event->activity)->toBe('Working...');
});

test('error event stores message', function () {
    $event = new ErrorEvent(message: 'Oops', code: 500);

    expect($event->message)->toBe('Oops')
        ->and($event->code)->toBe(500);
});

test('result event stores success info', function () {
    $event = new ResultEvent(success: true, durationMs: 5000.0, costUsd: 0.01);

    expect($event->success)->toBeTrue()
        ->and($event->durationMs)->toBe(5000.0)
        ->and($event->costUsd)->toBe(0.01);
});
