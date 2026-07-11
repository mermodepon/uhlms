<?php

namespace App\Filament\Resources\SupportInquiryResource\Pages;

use App\Filament\Resources\SupportInquiryResource;
use App\Models\SupportInquiryReply;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportInquiry extends ViewRecord
{
    protected static string $resource = SupportInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reply')
                ->label('Reply to Guest')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn () => $this->record->guest_account_id !== null)
                ->form([
                    Forms\Components\Textarea::make('message')
                        ->label('Your Reply')
                        ->required()
                        ->minLength(10)
                        ->maxLength(2000)
                        ->rows(5),
                ])
                ->action(function (array $data): void {
                    SupportInquiryReply::create([
                        'support_inquiry_id' => $this->record->id,
                        'user_id'            => auth()->id(),
                        'guest_account_id'   => null,
                        'message'            => $data['message'],
                    ]);

                    Notification::make()
                        ->title('Reply added — the guest will see it in their account.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
