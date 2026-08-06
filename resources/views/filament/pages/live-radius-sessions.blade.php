<x-filament-panels::page>
    <div class="space-y-4">
        <div class="p-4 bg-success-50 dark:bg-success-900/20 rounded-xl border border-success-200 dark:border-success-700">
            <p class="text-sm text-success-700 dark:text-success-300 font-medium">
                ● Live sessions auto-refresh every 30 seconds. Shows all currently connected PPPoE / Hotspot users.
            </p>
        </div>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
