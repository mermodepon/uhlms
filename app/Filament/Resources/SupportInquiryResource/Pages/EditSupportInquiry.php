<?php

namespace App\Filament\Resources\SupportInquiryResource\Pages;

use App\Filament\Resources\SupportInquiryResource;
use Filament\Resources\Pages\EditRecord;

class EditSupportInquiry extends EditRecord
{
    protected static string $resource = SupportInquiryResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['handled_by'] = auth()->id();
        $data['handled_at'] = now();

        $data['resolved_at'] = ($data['status'] ?? null) === \App\Models\SupportInquiry::STATUS_RESOLVED ? now() : null;

        return $data;
    }
}
