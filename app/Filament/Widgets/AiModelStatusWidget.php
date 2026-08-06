<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Http;

class AiModelStatusWidget extends Widget
{
    protected string $view = 'filament.widgets.ai-model-status-widget';
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    public $lastTrained = 'Unknown';
    public $accuracy = 0.0;

    public function mount()
    {
        try {
            $url = config('services.ai_microservice.url', 'http://fiberloop-ai:8001');
            $response = Http::timeout(5)->get("{$url}/status");
            if ($response->successful()) {
                $data = $response->json();
                $this->lastTrained = $data['last_trained'] ?? 'Never';
                $this->accuracy = $data['accuracy'] ?? 0.0;
            }
        } catch (\Exception $e) {
            $this->lastTrained = 'Service Unavailable';
        }
    }
}
