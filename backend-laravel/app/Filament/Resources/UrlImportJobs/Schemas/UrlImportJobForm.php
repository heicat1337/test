<?php

namespace App\Filament\Resources\UrlImportJobs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class UrlImportJobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('url')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('normalized_url')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('source_domain')
                    ->default(''),
                TextInput::make('page_title')
                    ->default(''),
                TextInput::make('status')
                    ->default('queued'),
                TextInput::make('current_step')
                    ->default('queued'),
                TextInput::make('progress_percent')
                    ->numeric()
                    ->default(0),
                Textarea::make('options_json')
                    ->default('')
                    ->columnSpanFull(),
                Textarea::make('result_json')
                    ->default('')
                    ->columnSpanFull(),
                Textarea::make('error_message')
                    ->default('')
                    ->columnSpanFull(),
                TextInput::make('created_by')
                    ->default(''),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('finished_at'),
            ]);
    }
}
