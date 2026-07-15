<?php

namespace App\Filament\Pages;

use App\Models\User;

class FeedbackAnalyticsReport extends Reports
{
    protected const REPORT_TYPE = 'feedback_analytics';

    protected const REPORT_PERMISSION = User::REPORT_FEEDBACK_ANALYTICS_VIEW;

    protected static ?string $slug = 'reports/feedback-analytics';

    protected static ?string $navigationLabel = 'Feedback Analytics';

    protected static ?int $navigationSort = 4;
}
