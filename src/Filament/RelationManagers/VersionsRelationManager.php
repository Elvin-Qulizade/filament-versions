<?php

namespace ElvinQulizade\Versions\Filament\RelationManagers;

use ElvinQulizade\Versions\Contracts\Versionable;
use ElvinQulizade\Versions\Models\Version;
use ElvinQulizade\Versions\Support\VersionDiffer;
use ElvinQulizade\Versions\Support\VersionSettings;
use Filament\Forms\Components\CheckboxList;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $icon = 'heroicon-o-clock';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament-versions::versions.tab.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('event')
            ->defaultSort('id', 'desc')
            // The owner record is saved by a sibling Livewire component (the
            // Edit page's form), which cannot notify this table directly, so
            // poll instead of going stale until the next full page load.
            ->poll('10s')
            ->headerActions([
                Action::make('manageExcludedFields')
                    ->label(__('filament-versions::versions.actions.manage_excluded_fields'))
                    ->icon('heroicon-o-eye-slash')
                    ->modalHeading(__('filament-versions::versions.excluded_fields.heading'))
                    ->modalDescription(__('filament-versions::versions.excluded_fields.description'))
                    ->fillForm(fn (): array => [
                        'excluded_fields' => VersionSettings::excludedFieldsFor($this->getOwnerRecord()->getMorphClass()),
                    ])
                    ->form([
                        CheckboxList::make('excluded_fields')
                            ->hiddenLabel()
                            ->options(fn (): array => array_combine(
                                $columns = Schema::getColumnListing($this->getOwnerRecord()->getTable()),
                                $columns,
                            ))
                            ->columns(2),
                    ])
                    ->action(function (array $data): void {
                        VersionSettings::setExcludedFieldsFor(
                            $this->getOwnerRecord()->getMorphClass(),
                            $data['excluded_fields'] ?? [],
                        );

                        Notification::make()
                            ->title(__('filament-versions::versions.excluded_fields.success'))
                            ->success()
                            ->send();
                    }),
            ])
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('filament-versions::versions.columns.when'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('event')
                    ->label(__('filament-versions::versions.columns.event'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("filament-versions::versions.events.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'restored' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('user.name')
                    ->label(__('filament-versions::versions.columns.user'))
                    ->default(__('filament-versions::versions.user.system')),
            ])
            ->actions([
                Action::make('viewDiff')
                    ->label(__('filament-versions::versions.actions.view_diff'))
                    ->icon('heroicon-o-eye')
                    ->modalHeading(__('filament-versions::versions.diff.heading'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament-versions::versions.actions.close'))
                    ->infolist(fn (Version $record) => static::diffSchema($record)),

                Action::make('restore')
                    ->label(__('filament-versions::versions.actions.restore'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('filament-versions::versions.restore.confirmation_heading'))
                    ->modalDescription(__('filament-versions::versions.restore.confirmation_description'))
                    ->visible(fn (Version $record): bool => static::canRestoreVersion($record))
                    // The owner record's edit form lives in a sibling Livewire
                    // component that has no way to learn the record changed
                    // here, so its fields would go stale — a save right after
                    // restoring would silently overwrite the restore with
                    // those stale values. Reload the page instead of trying
                    // to sync two independent components.
                    //
                    // url()->current() would resolve to this Livewire AJAX
                    // request's own endpoint (/livewire/update), not the page
                    // the browser is showing, so the actual page URL has to
                    // come from the Referer header instead.
                    ->successRedirectUrl(fn (): ?string => request()->header('referer'))
                    ->successNotificationTitle(__('filament-versions::versions.restore.success'))
                    // Filament 3 only notifies and redirects when a custom
                    // action calls success() itself.
                    ->action(function (Version $record, Action $action): void {
                        $versionable = $record->versionable;

                        if ($versionable instanceof Versionable) {
                            $versionable->restoreVersion($record);
                        }

                        $action->success();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('compare')
                    ->label(__('filament-versions::versions.actions.compare'))
                    ->icon('heroicon-o-arrows-right-left')
                    ->modalHeading(__('filament-versions::versions.diff.heading'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament-versions::versions.actions.close'))
                    ->deselectRecordsAfterCompletion()
                    // Filament only renders row-selection checkboxes at all
                    // once some bulk action reports itself visible for the
                    // current (possibly empty) selection. Gating visibility
                    // on count() === 2 made that never true at zero selected,
                    // so the checkboxes — and thus this action — could never
                    // be reached. Stay visible always; guard the count in the
                    // schema instead.
                    ->infolist(function (Collection $records): array {
                        if ($records->count() !== 2) {
                            return [
                                TextEntry::make('needs_two')
                                    ->hiddenLabel()
                                    ->state(__('filament-versions::versions.diff.select_two')),
                            ];
                        }

                        /** @var array<int, Version> $sorted */
                        $sorted = $records->sortBy('id')->values()->all();

                        return static::diffEntries($sorted[0]->data, $sorted[1]->data);
                    }),
            ]);
    }

    /**
     * @return array<TextEntry>
     */
    protected static function diffSchema(Version $record): array
    {
        $previous = Version::query()
            ->where('versionable_type', $record->versionable_type)
            ->where('versionable_id', $record->versionable_id)
            ->where('id', '<', $record->id)
            ->orderByDesc('id')
            ->first();

        return static::diffEntries($previous === null ? [] : $previous->data, $record->data);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<TextEntry>
     */
    protected static function diffEntries(array $before, array $after): array
    {
        $diff = VersionDiffer::diff($before, $after);

        if ($diff === []) {
            return [
                TextEntry::make('no_changes')
                    ->hiddenLabel()
                    ->state(__('filament-versions::versions.diff.no_changes')),
            ];
        }

        return collect($diff)
            ->map(fn (array $change, string $field) => TextEntry::make($field)
                ->label($field)
                ->state(sprintf(
                    '%s → %s',
                    static::formatDiffValue($change['old']),
                    static::formatDiffValue($change['new']),
                )))
            ->values()
            ->all();
    }

    protected static function formatDiffValue(mixed $value): string
    {
        return match (true) {
            $value === null => __('filament-versions::versions.diff.empty_value'),
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => json_encode($value) ?: '',
        };
    }

    protected static function canRestoreVersion(Version $record): bool
    {
        $callback = config('filament-versions.authorize_restore');

        return $callback === null || (bool) call_user_func($callback, $record);
    }
}
