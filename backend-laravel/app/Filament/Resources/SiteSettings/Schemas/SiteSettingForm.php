<?php

namespace App\Filament\Resources\SiteSettings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SiteSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('setting_key')
                ->label('Key')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true)
                ->disabledOn('edit')
                ->helperText('如 site_name / site_description / featured_limit'),

            Textarea::make('setting_value')
                ->label('Value')
                ->rows(4)
                ->columnSpanFull(),
        ]);
    }
}
