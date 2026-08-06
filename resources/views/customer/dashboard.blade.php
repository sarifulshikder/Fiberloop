@extends('customer.layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="space-y-6">
        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-primary-600 to-primary-800 rounded-xl p-6 text-white">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div class="mb-4 lg:mb-0">
                    <h2 class="text-2xl font-bold mb-2">Welcome back, {{ $customer->first_name ?? 'Customer' }}!</h2>
                    <p class="text-primary-100">Manage your account, view invoices, make payments, and track your usage.</p>
                </div>
                <div class="flex space-x-4">
                    <a href="{{ route('customer.invoices.index') }}" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        View Invoices
                    </a>
                    <a href="{{ route('customer.payments.create') }}" class="bg-white hover:bg-gray-100 text-slate-800 px-4 py-2 rounded-lg font-medium transition-colors">
                        Make Payment
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Account Balance -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Account Balance</h3>
                            <p class="text-2xl font-bold text-green-600">BDT {{ number_format($customer->wallet_balance / 100, 2) }}</p>
                        </div>
                    </div>
                </div>
                <p class="text-sm text-gray-500">Available balance for future payments</p>
            </div>

            <!-- Current Package -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Current Package</h3>
                            @if($subscription)
                                <p class="text-2xl font-bold text-blue-600">{{ $subscription->package?->name ?? 'N/A' }}</p>
                            @else
                                <p class="text-2xl font-bold text-gray-400">No Active</p>
                            @endif
                        </div>
                    </div>
                </div>
                @if($subscription)
                    <p class="text-sm text-gray-500">
                        {{ $subscription->package?->download_speed ?? 0 }} Mbps |
                        BDT {{ number_format($subscription->final_price / 100, 2) }} / month
                    </p>
                @else
                    <p class="text-sm text-gray-500">No active subscription</p>
                @endif
            </div>

            <!-- Monthly Usage -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Monthly Usage</h3>
                            <p class="text-2xl font-bold text-purple-600">{{ $usage['current_month_usage_formatted'] ?? '0 B' }}</p>
                        </div>
                    </div>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-purple-600 h-2 rounded-full" style="width: {{ $usage['fup_usage_percentage'] ?? 0 }}%"></div>
                </div>
                <p class="text-sm text-gray-500 mt-2">{{ $usage['fup_usage_percentage'] ?? 0 }}% of {{ $usage['fup_limit_formatted'] ?? '0 B' }}</p>
            </div>

            <!-- Session Status -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 @if($usage['is_online']) bg-green-100 @else bg-gray-100 @endif rounded-full flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 @if($usage['is_online']) text-green-600 @else text-gray-400 @endif" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Connection Status</h3>
                            <p class="text-2xl font-bold @if($usage['is_online']) text-green-600 @else text-gray-400 @endif">
                                @if($usage['is_online']) Online @else Offline @endif
                            </p>
                        </div>
                    </div>
                </div>
                <p class="text-sm text-gray-500">
                    @if($usage['active_session'])
                        Active since {{ $usage['active_session']['session_start'] ?? 'N/A' }}
                    @else
                        Last session: {{ $usage['last_updated'] ?? 'N/A' }}
                    @endif
                </p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="{{ route('customer.invoices.index') }}" class="flex flex-col items-center p-4 rounded-lg hover:bg-gray-50 transition-colors">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mb-2">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-600">View Invoices</span>
                </a>
                <a href="{{ route('customer.payments.create') }}" class="flex flex-col items-center p-4 rounded-lg hover:bg-gray-50 transition-colors">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mb-2">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H4a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-600">Make Payment</span>
                </a>
                <a href="{{ route('customer.tickets.create') }}" class="flex flex-col items-center p-4 rounded-lg hover:bg-gray-50 transition-colors">
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mb-2">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-600">Create Ticket</span>
                </a>
                <a href="{{ route('customer.chat.index') }}" class="flex flex-col items-center p-4 rounded-lg hover:bg-gray-50 transition-colors">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mb-2">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-600">Live Chat</span>
                </a>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Outstanding Invoices -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Outstanding Invoices</h3>
                    <a href="{{ route('customer.invoices.index') }}" class="text-sm text-primary-600 hover:text-primary-800 font-medium">
                        View All
                    </a>
                </div>
                @if($invoices->count() > 0)
                    <div class="space-y-4">
                        @foreach($invoices as $invoice)
                            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50">
                                <div class="flex-1">
                                    <p class="font-medium text-gray-800">{{ $invoice->invoice_number }}</p>
                                    <p class="text-sm text-gray-500">
                                        Due: {{ $invoice->due_date->format('M d, Y') }} |
                                        BDT {{ number_format($invoice->outstanding_amount / 100, 2) }}
                                    </p>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-medium 
                                    @if($invoice->status->value === 'overdue') bg-red-100 text-red-600
                                    @elseif($invoice->status->value === 'due_soon') bg-yellow-100 text-yellow-600
                                    @else bg-green-100 text-green-600 @endif">
                                    {{ ucfirst($invoice->status->value) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-gray-500">No outstanding invoices</p>
                    </div>
                @endif
            </div>

            <!-- Recent Payments & Tickets -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Activity</h3>
                </div>
                <div class="space-y-4">
                    @foreach($recentPayments as $payment)
                        <div class="flex items-center space-x-3 p-2">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-800">Payment of BDT {{ number_format($payment->amount / 100, 2) }}</p>
                                <p class="text-xs text-gray-500">{{ $payment->paid_at->format('M d, Y h:i a') }}</p>
                            </div>
                        </div>
                    @endforeach
                    @foreach($openTickets as $ticket)
                        <div class="flex items-center space-x-3 p-2">
                            <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-800">Ticket: {{ $ticket->subject }}</p>
                                <p class="text-xs text-gray-500">{{ $ticket->created_at->format('M d, Y h:i a') }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($recentPayments->count() === 0 && $openTickets->count() === 0)
                    <div class="text-center py-8">
                        <p class="text-gray-500">No recent activity</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
