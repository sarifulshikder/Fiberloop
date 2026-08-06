<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Filament\Facades\Filament;
use Filament\Tables\Table;

Filament::setCurrentPanel(Filament::getPanel('admin'));

$resources = Filament::getResources();

$errors = [];

foreach ($resources as $resource) {
    try {
        echo "Testing $resource...\n";

        // Test Table
        if (method_exists($resource, 'table')) {
            $mockComponent = new class () extends \Livewire\Component implements \Filament\Tables\Contracts\HasTable {
                use \Filament\Tables\Concerns\InteractsWithTable;
                public function makeFilamentTranslatableContentDriver(): ?\Filament\Support\Contracts\TranslatableContentDriver
                {
                    return null;
                }
            };
            $table = Table::make($mockComponent);

            // This might be tricky because Filament tables require a proper Livewire component context.
            // Let's just try to call the static table() method.
            try {
                $resource::table(Table::make($mockComponent));
            } catch (\Exception $e) {
                // If it fails because of context, that's fine, but catch TypeErrors
                if ($e instanceof TypeError) {
                    $errors[] = "$resource Table TypeError: " . $e->getMessage();
                }
            } catch (TypeError $e) {
                $errors[] = "$resource Table TypeError: " . $e->getMessage();
            }
        }

        // Test Form
        if (method_exists($resource, 'form')) {
            $mockComponent = new class () extends \Livewire\Component implements \Filament\Forms\Contracts\HasForms {
                use \Filament\Forms\Concerns\InteractsWithForms;
            };
            try {
                $resource::form(\Filament\Schemas\Schema::make($mockComponent));
            } catch (\Exception $e) {
                if ($e instanceof TypeError) {
                    $errors[] = "$resource Form TypeError: " . $e->getMessage();
                }
            } catch (TypeError $e) {
                $errors[] = "$resource Form TypeError: " . $e->getMessage();
            }
        }
    } catch (\Throwable $e) {
        $errors[] = "$resource: " . $e->getMessage();
    }
}

if (empty($errors)) {
    echo "\nAll resources loaded without TypeErrors.\n";
} else {
    echo "\nErrors found:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
}
