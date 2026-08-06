<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-4">
            <div class="p-3 bg-primary-500/10 rounded-xl">
                <x-heroicon-o-cpu-chip class="w-6 h-6 text-primary-500" />
            </div>
            <div>
                <h2 class="text-lg font-bold">AI Model Status</h2>
                <p class="text-sm text-gray-500">Churn & Anomaly Detection Models</p>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-4">
            <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-800">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Trained</div>
                <div class="mt-1 text-lg font-semibold">{{ \Carbon\Carbon::parse($lastTrained)->diffForHumans() }}</div>
                <div class="text-xs text-gray-400">{{ $lastTrained }}</div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-800">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Model Accuracy</div>
                <div class="mt-1 text-lg font-semibold">{{ number_format($accuracy * 100, 1) }}%</div>
                <div class="text-xs text-gray-400">Training set score</div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
