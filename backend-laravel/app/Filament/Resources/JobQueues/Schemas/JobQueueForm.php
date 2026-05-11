<?php

namespace App\Filament\Resources\JobQueues\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class JobQueueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('task_id')
                    ->relationship('task', 'name')
                    ->required(),
                TextInput::make('job_type')
                    ->required()
                    ->default('generate_article'),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                Textarea::make('payload')
                    ->default('')
                    ->columnSpanFull(),
                TextInput::make('attempt_count')
                    ->numeric()
                    ->default(0),
                TextInput::make('max_attempts')
                    ->numeric()
                    ->default(3),
                DateTimePicker::make('available_at')
                    ->required(),
                DateTimePicker::make('claimed_at'),
                DateTimePicker::make('finished_at'),
                TextInput::make('worker_id')
                    ->default(''),
                Textarea::make('error_message')
                    ->default('')
                    ->columnSpanFull(),
            ]);
    }
}
