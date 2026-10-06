<?php

namespace ElvinQulizade\Versions\Tests\Fixtures;

use ElvinQulizade\Versions\Filament\RelationManagers\VersionsRelationManager;
use ElvinQulizade\Versions\Tests\Fixtures\PostResource\Pages;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('title')->required(),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
