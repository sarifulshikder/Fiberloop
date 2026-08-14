<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Services\Ai\AiMicroservice;
use Filament\Pages\Page;

class AiAnalyticsDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';
    protected string $view = 'filament.pages.ai-analytics-dashboard';
    protected static ?string $navigationLabel = 'AI Analytics';
    protected static string|\UnitEnum|null $navigationGroup = 'AI Analytics';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'AI & Analytics Dashboard';

    public array $highRiskCustomers = [];
    public array $anomalousCustomers = [];
    public array $forecast = [];
    public string $modelLastTrained = 'Never';
    public float $modelAccuracy = 0.0;

    public function mount()
    {
        $this->highRiskCustomers = Customer::where('is_high_risk', true)
            ->orderByDesc('churn_score')
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'status', 'churn_score'])
            ->toArray();

        $this->anomalousCustomers = Customer::where('has_anomaly', true)
            ->orderBy('anomaly_score')
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'status', 'anomaly_score'])
            ->toArray();

        try {
            $ai = new AiMicroservice();
            $this->forecast = $ai->getRevenueForecast();

            $url = config('services.ai_microservice.url', 'http://fiberloop-ai:8001');
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get("{$url}/status");
            if ($response->successful()) {
                $data = $response->json();
                $this->modelLastTrained = $data['last_trained'] ?? 'Never';
                $this->modelAccuracy = $data['accuracy'] ?? 0.0;
            }
        } catch (\Exception $e) {
            $this->modelLastTrained = 'Service Unavailable';
        }
    }
}
