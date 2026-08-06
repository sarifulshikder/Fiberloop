<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Ai\AiMicroservice;
use Illuminate\Console\Command;

class RunAiAnalysis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:run-analysis';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger AI microservice to predict churn and detect anomalies, and update customer records';

    /**
     * Execute the console command.
     */
    public function handle(AiMicroservice $ai)
    {
        $this->info('Starting AI Analysis...');

        $this->info('Triggering churn model training...');
        $ai->triggerChurnTraining();

        sleep(2);

        $this->info('Fetching churn predictions...');
        $churnData = $ai->getChurnPredictions();

        $churnCount = 0;
        foreach ($churnData as $data) {
            Customer::where('id', $data['customer_id'])->update([
                'churn_score' => $data['churn_probability'],
                'is_high_risk' => $data['is_high_risk'],
            ]);
            $churnCount++;
        }
        $this->info("Updated {$churnCount} customers with churn scores.");

        $this->info('Fetching anomaly detections...');
        $anomalyData = $ai->getAnomalyDetections();

        $anomCount = 0;
        foreach ($anomalyData as $data) {
            Customer::where('id', $data['customer_id'])->update([
                'has_anomaly' => $data['is_anomalous'],
                'anomaly_score' => $data['anomaly_score'],
            ]);
            $anomCount++;
        }
        $this->info("Updated {$anomCount} customers with anomaly flags.");

        $this->info('AI Analysis completed.');
    }
}
