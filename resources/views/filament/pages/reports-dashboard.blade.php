<x-filament-panels::page>
    <div class="space-y-6">
        <p class="text-gray-500 dark:text-gray-400">Generate and export comprehensive business reports. Use the buttons above to download data.</p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-filament::card>
                <h2 class="text-lg font-bold">Aging Report</h2>
                <p>Export outstanding dues categorized by age: 0-30 days, 31-60 days, 61-90 days, and 90+ days.</p>
            </x-filament::card>
            
            <x-filament::card>
                <h2 class="text-lg font-bold">Revenue Report</h2>
                <p>Export monthly revenue totals for paid invoices.</p>
            </x-filament::card>
            
            <x-filament::card>
                <h2 class="text-lg font-bold">Churn Report</h2>
                <p>Export the count of customers who terminated their service per month.</p>
            </x-filament::card>
        </div>
    </div>
</x-filament-panels::page>
