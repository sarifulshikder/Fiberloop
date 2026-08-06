<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Fiberloop - Customer Portal')</title>
    <meta name="description" content="Fiberloop ISP Customer Self-Service Portal">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        secondary: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        [x-cloak] { display: none !important; }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
    
    @stack('styles')
</head>
<body class="font-sans antialiased bg-gray-50 text-slate-800">
    <div class="min-h-screen">
        <!-- Mobile Navigation -->
        <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50">
            <div class="flex justify-around items-center py-2 px-4">
                <a href="{{ route('customer.dashboard') }}" class="flex flex-col items-center py-2 px-4 text-sm font-medium text-slate-600 hover:text-primary-600 @if(request()->is('customer') || request()->is('customer/dashboard')) text-primary-600 @endif">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('customer.invoices.index') }}" class="flex flex-col items-center py-2 px-4 text-sm font-medium text-slate-600 hover:text-primary-600 @if(request()->is('customer/invoices*')) text-primary-600 @endif">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Invoices</span>
                </a>
                <a href="{{ route('customer.payments.index') }}" class="flex flex-col items-center py-2 px-4 text-sm font-medium text-slate-600 hover:text-primary-600 @if(request()->is('customer/payments*')) text-primary-600 @endif">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span>Payments</span>
                </a>
                <a href="{{ route('customer.tickets.index') }}" class="flex flex-col items-center py-2 px-4 text-sm font-medium text-slate-600 hover:text-primary-600 @if(request()->is('customer/tickets*')) text-primary-600 @endif">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Support</span>
                </a>
                <a href="{{ route('customer.profile.edit') }}" class="flex flex-col items-center py-2 px-4 text-sm font-medium text-slate-600 hover:text-primary-600 @if(request()->is('customer/profile*')) text-primary-600 @endif">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span>Profile</span>
                </a>
            </div>
        </nav>

        <!-- Desktop Navigation -->
        <div class="hidden lg:flex">
            <!-- Sidebar -->
            <aside class="fixed left-0 top-0 h-full w-64 bg-slate-800 text-white z-40 transition-all duration-300">
                <div class="flex items-center justify-between h-16 px-6 border-b border-slate-700">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-primary-600 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9 9 0 100-18 9 9 0 000 18z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v18M3 12h18" />
                            </svg>
                        </div>
                        <span class="text-xl font-bold">Fiberloop</span>
                    </div>
                </div>
                
                <nav class="flex flex-col py-6 px-4 space-y-2">
                    <a href="{{ route('customer.dashboard') }}" class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors @if(request()->is('customer') || request()->is('customer/dashboard')) bg-primary-600 text-white @else hover:bg-slate-700 text-slate-300 @endif">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>
                    
                    <a href="{{ route('customer.invoices.index') }}" class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors @if(request()->is('customer/invoices*')) bg-primary-600 text-white @else hover:bg-slate-700 text-slate-300 @endif">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Invoices
                    </a>
                    
                    <a href="{{ route('customer.payments.index') }}" class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors @if(request()->is('customer/payments*')) bg-primary-600 text-white @else hover:bg-slate-700 text-slate-300 @endif">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Payments
                        @if($unreadCount = Auth::user()?->customer?->invoices()->whereIn('status', ['draft', 'sent', 'overdue'])->where('due_date', '<=', now())->count())
                            <span class="ml-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-1">{{ $unreadCount }}</span>
                        @endif
                    </a>
                    
                    <a href="{{ route('customer.tickets.index') }}" class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors @if(request()->is('customer/tickets*')) bg-primary-600 text-white @else hover:bg-slate-700 text-slate-300 @endif">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Support Tickets
                        @if($unreadTickets = Auth::user()?->customer?->tickets()->where('status', 'open')->count())
                            <span class="ml-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-1">{{ $unreadTickets }}</span>
                        @endif
                    </a>
                    
                    <a href="{{ route('customer.usage.index') }}" class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors @if(request()->is('customer/usage*')) bg-primary-600 text-white @else hover:bg-slate-700 text-slate-300 @endif">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Usage
                    </a>
                    
                    <a href="{{ route('customer.chat.index') }}" class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors @if(request()->is('customer/chat*')) bg-primary-600 text-white @else hover:bg-slate-700 text-slate-300 @endif">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        Live Chat
                        @if($unreadChat = Auth::user()?->customer?->chatConversations()->where('is_read_by_customer', false)->count())
                            <span class="ml-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-1">{{ $unreadChat }}</span>
                        @endif
                    </a>
                    
                    <div class="pt-4 border-t border-slate-700 mt-4">
                        <a href="{{ route('customer.profile.edit') }}" class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors @if(request()->is('customer/profile*')) bg-primary-600 text-white @else hover:bg-slate-700 text-slate-300 @endif">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Profile
                        </a>
                        <a href="{{ route('customer.logout') }}" class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors hover:bg-slate-700 text-slate-300">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Logout
                        </a>
                    </div>
                </nav>
            </aside>

            <!-- Main Content Area -->
            <main class="flex-1 ml-64 min-h-screen">
                <!-- Header -->
                <header class="fixed top-0 left-64 right-0 bg-white border-b border-gray-200 z-30">
                    <div class="flex items-center justify-between h-16 px-6">
                        <div class="flex items-center">
                            <h1 class="text-xl font-semibold text-slate-800">@yield('title', 'Dashboard')</h1>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <button class="flex items-center space-x-2 p-2 rounded-full hover:bg-gray-100">
                                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                    @if(Auth::user()?->customer?->unreadNotifications())
                                        <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">
                                            {{ Auth::user()?->customer?->unreadNotifications() }}
                                        </span>
                                    @endif
                                </button>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center">
                                    <span class="text-primary-600 font-semibold">
                                        {{ strtoupper(substr(Auth::user()?->name ?? 'U', 0, 2)) }}
                                    </span>
                                </div>
                                <span class="text-sm font-medium">{{ Auth::user()?->name ?? 'User' }}</span>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Page Content -->
                <div class="p-6 mt-16">
                    @yield('content')
                </div>
            </main>
        </div>

        <!-- Mobile Content Area (above bottom nav) -->
        <div class="lg:hidden pt-16 pb-16">
            @yield('content')
        </div>
    </div>

    <!-- Toast Notification -->
    @if(session('success') || session('error') || session('warning'))
        <div id="toast" x-data="{ show: true }" x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 transform translate-y-0" x-transition:leave-end="opacity-0 transform translate-y-4" class="fixed top-4 right-4 z-50" x-init="setTimeout(() => show = false, 5000)">
            <div class="rounded-lg shadow-lg px-4 py-3 bg-white border border-gray-200 flex items-center space-x-3">
                <div class="flex-shrink-0">
                    @if(session('success'))
                        <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    @elseif(session('error'))
                        <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    @else
                        <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    @endif
                </div>
                <div>
                    <p class="text-sm font-medium @if(session('success')) text-green-700 @elseif(session('error')) text-red-700 @else text-yellow-700 @endif">
                        {{ session('success') ?? session('error') ?? session('warning') }}
                    </p>
                </div>
                <button @click="show = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    @endif

    <script>
        // Toast dismissible
        document.addEventListener('DOMContentLoaded', function() {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.addEventListener('click', function(e) {
                    if (e.target.tagName === 'BUTTON') {
                        toast.style.display = 'none';
                    }
                });
            }
        });
    </script>
    
    @stack('scripts')
</body>
</html>
