<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiMicroservice
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.ai_microservice.url', 'http://fiberloop-ai:8001');
    }

    public function triggerChurnTraining(): bool
    {
        try {
            $response = Http::post("{$this->baseUrl}/train/churn");
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('AI Microservice error (train churn): ' . $e->getMessage());
            return false;
        }
    }

    public function getChurnPredictions(): array
    {
        try {
            $response = Http::timeout(60)->get("{$this->baseUrl}/predict/churn");
            if ($response->successful()) {
                return $response->json();
            }
            return [];
        } catch (\Exception $e) {
            Log::error('AI Microservice error (predict churn): ' . $e->getMessage());
            return [];
        }
    }

    public function getAnomalyDetections(): array
    {
        try {
            $response = Http::timeout(60)->get("{$this->baseUrl}/detect/anomaly");
            if ($response->successful()) {
                return $response->json();
            }
            return [];
        } catch (\Exception $e) {
            Log::error('AI Microservice error (detect anomaly): ' . $e->getMessage());
            return [];
        }
    }

    public function getRevenueForecast(): array
    {
        try {
            $response = Http::timeout(60)->get("{$this->baseUrl}/forecast/revenue");
            if ($response->successful()) {
                return $response->json();
            }
            return [];
        } catch (\Exception $e) {
            Log::error('AI Microservice error (forecast revenue): ' . $e->getMessage());
            return [];
        }
    }
}
