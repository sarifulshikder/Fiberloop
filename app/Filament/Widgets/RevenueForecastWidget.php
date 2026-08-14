<?php

namespace App\Filament\Widgets;

use App\Services\Ai\AiMicroservice;
use Filament\Widgets\ChartWidget;

class RevenueForecastWidget extends ChartWidget
{
    protected ?string $heading = 'Revenue Forecast (Next 6 Months)';
    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $ai = new AiMicroservice();
        $forecasts = $ai->getRevenueForecast();

        $labels = [];
        $data = [];

        foreach ($forecasts as $f) {
            $labels[] = $f['month'];
            // Convert back to BDT from poysha
            $data[] = $f['predicted_revenue'] / 100;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Predicted Revenue (BDT)',
                    'data' => $data,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
