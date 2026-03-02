<?php

namespace App\Filament\Resources\MessageResource\Pages;

use App\Filament\Pages\CreateRedirectToIndex;
use App\Filament\Resources\MessageResource;

class CreateMessage extends CreateRedirectToIndex
{
    protected static string $resource = MessageResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['sender_id'] = auth()->id();
        $data['sender_type'] = auth()->user()->role;
        
        return $data;
    }
}
