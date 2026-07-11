<?php

namespace App\Filament\Pages;

class FeedbackAnalyticsReport extends Reports
{
    protected const REPORT_TYPE = 'feedback_analytics';

    protected static ?string $slug = 'reports/feedback-analytics';

    protected static ?string $navigationLabel = 'Feedback Analytics';

    protected static ?int $navigationSort = 4;
}
