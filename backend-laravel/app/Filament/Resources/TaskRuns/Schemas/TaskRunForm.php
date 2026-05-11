<?php

namespace App\Filament\Resources\TaskRuns\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TaskRunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('task_id')
                    ->relationship('task', 'name')
                    ->required(),
                TextInput::make('job_id')
                    ->numeric(),
                TextInput::make('status')
                    ->required(),
                Select::make('article_id')
                    ->relationship('article', 'title'),
                Textarea::make('error_message')
                    ->default('')
                    ->columnSpanFull(),
                TextInput::make('duration_ms')
                    ->numeric()
                    ->default(0),
                Textarea::make('meta')
                    ->default('')
                    ->columnSpanFull(),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('finished_at'),
            ]);
    }
}
