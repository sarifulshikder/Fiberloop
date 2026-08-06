<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'admin@fiberloop.com')->first();
echo "User: {$user->email}\n";

$panel = Filament\Facades\Filament::getPanel('admin');
echo "Panel: {$panel->getId()}\n";

$allowed = $user->canAccessPanel($panel);
echo "canAccessPanel: " . ($allowed ? 'true' : 'false') . "\n";
echo "Roles: " . $user->roles->pluck('name')->implode(', ') . "\n";
echo "hasAnyRole(['super_admin']): " . ($user->hasAnyRole(['super_admin']) ? 'true' : 'false') . "\n";
