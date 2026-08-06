<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Model Status Card --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-heroicon-o-cpu-chip class="w-8 h-8 text-primary-500" />
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Model Last Trained</div>
                        <div class="font-semibold">
                            @if($modelLastTrained === 'Service Unavailable' || $modelLastTrained === 'Never')
                                {{ $modelLastTrained }}
                            @else
                                {{ \Carbon\Carbon::parse($modelLastTrained)->diffForHumans() }}
                            @endif
                        </div>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-heroicon-o-chart-bar class="w-8 h-8 text-success-500" />
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Model Accuracy (Training Set)</div>
                        <div class="font-semibold">{{ number_format($modelAccuracy * 100, 1) }}%</div>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-heroicon-o-exclamation-triangle class="w-8 h-8 text-danger-500" />
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">High-Risk Customers</div>
                        <div class="font-semibold">{{ count($highRiskCustomers) }} flagged</div>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- High-Risk Churn Customers --}}
        <x-filament::section heading="High Churn Risk Customers (Top 10)" description="Customers with predicted churn probability > 60%. Prioritize for retention.">
            @if(empty($highRiskCustomers))
                <p class="text-sm text-gray-500">No high-risk customers detected. Run <code>php artisan ai:run-analysis</code> to update scores.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Customer</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Churn Score</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($highRiskCustomers as $customer)
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <a href="{{ route('filament.admin.resources.customers.edit', $customer['id']) }}" class="text-primary-500 hover:underline">
                                            {{ $customer['first_name'] }} {{ $customer['last_name'] }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap capitalize">{{ $customer['status'] }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-danger-100 text-danger-800">
                                            {{ number_format($customer['churn_score'] * 100, 1) }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        {{-- Anomaly Detection --}}
        <x-filament::section heading="Anomalous Usage / Payment Patterns (Top 10)" description="Accounts flagged for unusual behaviour. Review for potential fraud or account sharing.">
            @if(empty($anomalousCustomers))
                <p class="text-sm text-gray-500">No anomalies detected yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Customer</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Anomaly Score</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($anomalousCustomers as $customer)
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <a href="{{ route('filament.admin.resources.customers.edit', $customer['id']) }}" class="text-primary-500 hover:underline">
                                            {{ $customer['first_name'] }} {{ $customer['last_name'] }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap capitalize">{{ $customer['status'] }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800">
                                            {{ number_format($customer['anomaly_score'], 4) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        {{-- Revenue Forecast --}}
        <x-filament::section heading="Revenue Forecast (Next 6 Months)" description="Projected subscribers and revenue based on current growth trends.">
            @if(empty($forecast))
                <p class="text-sm text-gray-500">Forecast data unavailable. AI service may be offline.</p>
            @else
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                    @foreach($forecast as $f)
                        <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-800">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $f['month'] }}</div>
                            <div class="mt-1 text-lg font-bold">৳{{ number_format($f['predicted_revenue'] / 100) }}</div>
                            <div class="text-xs text-gray-400">{{ number_format($f['predicted_subscribers']) }} subscribers</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- Refresh Note --}}
        <x-filament::section>
            <div class="flex items-center gap-3 text-sm text-gray-500">
                <x-heroicon-o-information-circle class="w-5 h-5 flex-shrink-0" />
                <span>AI scores are updated weekly by the <code>ai:run-analysis</code> scheduled command. To refresh now, run: <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">docker exec fiberloop-app php artisan ai:run-analysis</code></span>
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
