<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ReportsDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static string|\UnitEnum|null $navigationGroup = 'Reports';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pages.reports-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_aging')
                ->label('Export Aging Report (CSV)')
                ->action(fn () => $this->exportAgingReport()),
            Action::make('export_revenue')
                ->label('Export Revenue Report (CSV)')
                ->action(fn () => $this->exportRevenueReport()),
            Action::make('export_churn')
                ->label('Export Churn Report (CSV)')
                ->action(fn () => $this->exportChurnReport()),
        ];
    }

    protected function exportAgingReport()
    {
        $now = now();
        $invoices = Invoice::where('status', 'unpaid')->get();

        $data = [
            '0-30' => 0,
            '31-60' => 0,
            '61-90' => 0,
            '90+' => 0,
        ];

        foreach ($invoices as $invoice) {
            $days = $now->diffInDays($invoice->due_date);
            $amount = $invoice->total / 100;
            if ($days <= 30) {
                $data['0-30'] += $amount;
            } elseif ($days <= 60) {
                $data['31-60'] += $amount;
            } elseif ($days <= 90) {
                $data['61-90'] += $amount;
            } else {
                $data['90+'] += $amount;
            }
        }

        $csv = "Age Bracket,Outstanding Amount (BDT)\n";
        foreach ($data as $bracket => $amount) {
            $csv .= "{$bracket},{$amount}\n";
        }

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'aging_report.csv');
    }

    protected function exportRevenueReport()
    {
        // Revenue by month
        $revenues = Invoice::select(DB::raw("DATE_TRUNC('month', created_at) as month"), DB::raw('SUM(total) as total'))
            ->where('status', 'paid')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->get();

        $csv = "Month,Revenue (BDT)\n";
        foreach ($revenues as $rev) {
            $month = Carbon::parse($rev->month)->format('Y-m');
            $total = $rev->total / 100;
            $csv .= "{$month},{$total}\n";
        }

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'revenue_report.csv');
    }

    protected function exportChurnReport()
    {
        // Simplified churn by month (terminated customers)
        $churns = Customer::select(DB::raw("DATE_TRUNC('month', updated_at) as month"), DB::raw('COUNT(id) as count'))
            ->where('status', 'terminated')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->get();

        $csv = "Month,Churned Customers\n";
        foreach ($churns as $churn) {
            $month = Carbon::parse($churn->month)->format('Y-m');
            $csv .= "{$month},{$churn->count}\n";
        }

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'churn_report.csv');
    }
}
