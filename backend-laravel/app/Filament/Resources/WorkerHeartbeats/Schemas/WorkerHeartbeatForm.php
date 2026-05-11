<?php

namespace App\Filament\Resources\WorkerHeartbeats\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class WorkerHeartbeatForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('status')
                    ->required()
                    ->default('idle'),
                TextInput::make('current_job_id')
                    ->numeric(),
                DateTimePicker::make('last_seen_at')
                    ->required(),
                Textarea::make('meta')
                    ->default('')
                    ->columnSpanFull(),
            ]);
    }
}
