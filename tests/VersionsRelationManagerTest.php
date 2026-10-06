<?php

use ElvinQulizade\Versions\Filament\RelationManagers\VersionsRelationManager;
use ElvinQulizade\Versions\Tests\Fixtures\Post;
use ElvinQulizade\Versions\Tests\Fixtures\PostResource\Pages\EditPost;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel($this->panel());
});

it('renders the history tab with the version timeline', function () {
    $post = Post::create(['title' => 'Hello', 'body' => 'World']);
    $post->update(['title' => 'Updated']);

    Livewire::test(VersionsRelationManager::class, [
        'ownerRecord' => $post,
        'pageClass' => EditPost::class,
    ])->assertSuccessful();
});

it('restores a version through the restore action', function () {
    $post = Post::create(['title' => 'Hello', 'body' => 'World']);
    $original = $post->latestVersion();

    $post->update(['title' => 'Updated']);

    Livewire::test(VersionsRelationManager::class, [
        'ownerRecord' => $post,
        'pageClass' => EditPost::class,
    ])
        ->callTableAction('restore', $original)
        ->assertSuccessful();

    expect($post->fresh()->title)->toBe('Hello');
});

it('hides the restore action when authorization denies it', function () {
    config()->set('filament-versions.authorize_restore', fn () => false);

    $post = Post::create(['title' => 'Hello']);

    Livewire::test(VersionsRelationManager::class, [
        'ownerRecord' => $post,
        'pageClass' => EditPost::class,
    ])->assertTableActionHidden('restore', $post->latestVersion());
});

it('keeps the compare action visible at every selection count', function () {
    // Filament only renders row-selection checkboxes once some bulk action
    // reports itself visible for the current (possibly empty) selection —
    // so this action must stay visible even at zero selected, or the
    // checkboxes needed to ever select two rows would never appear.
    $post = Post::create(['title' => 'v1']);
    $post->update(['title' => 'v2']);
    $post->update(['title' => 'v3']);

    $versions = $post->versions()->pluck('id');

    Livewire::test(VersionsRelationManager::class, [
        'ownerRecord' => $post,
        'pageClass' => EditPost::class,
    ])
        ->assertTableBulkActionVisible('compare')
        ->set('selectedTableRecords', [$versions[0]])
        ->assertTableBulkActionVisible('compare')
        ->set('selectedTableRecords', [$versions[0], $versions[1]])
        ->assertTableBulkActionVisible('compare');
});

it('opens the compare modal for exactly two selected versions', function () {
    $post = Post::create(['title' => 'v1']);
    $post->update(['title' => 'v2']);
    $post->update(['title' => 'v3']);

    $versions = $post->versions()->pluck('id');

    Livewire::test(VersionsRelationManager::class, [
        'ownerRecord' => $post,
        'pageClass' => EditPost::class,
    ])
        ->callTableBulkAction('compare', [$versions[0], $versions[2]])
        ->assertSuccessful();
});

it('excludes a field from future snapshots once managed in the panel', function () {
    $post = Post::create(['title' => 'Hello', 'body' => 'World']);

    Livewire::test(VersionsRelationManager::class, [
        'ownerRecord' => $post,
        'pageClass' => EditPost::class,
    ])
        ->callTableAction('manageExcludedFields', data: ['excluded_fields' => ['body']])
        ->assertSuccessful();

    $post->update(['body' => 'Universe']);

    expect($post->latestVersion()->data)->not->toHaveKey('body');
});

it('shows the field diff in the view and compare modals', function () {
    // Filament 3 puts modal content in ->infolist(), not ->schema(). If that
    // wiring breaks, the modals open empty and the action tests above still pass.
    $post = Post::create(['title' => 'v1']);
    $post->update(['title' => 'v2']);
    $post->update(['title' => 'v3']);

    $versions = $post->versions()->orderBy('id')->pluck('id');

    Livewire::test(VersionsRelationManager::class, [
        'ownerRecord' => $post,
        'pageClass' => EditPost::class,
    ])
        ->mountTableAction('viewDiff', $versions[1])
        ->assertSee('v1 → v2');

    Livewire::test(VersionsRelationManager::class, [
        'ownerRecord' => $post,
        'pageClass' => EditPost::class,
    ])
        ->mountTableBulkAction('compare', [$versions[0], $versions[2]])
        ->assertSee('v1 → v3');
});
