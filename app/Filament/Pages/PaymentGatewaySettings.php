<?php

namespace App\Filament\Pages;

use App\Models\PaymentGatewaySetting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentGatewaySettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Payment Gateways';
    protected static ?string $title = 'Payment Gateways';
    protected string $view = 'filament.pages.payment-gateway-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->hasRole('super_admin') || $user->hasRole('admin'));
    }

    /**
     * Gateway field metadata: gateway => [credential field names].
     */
    protected function gatewayFields(): array
    {
        return [
            'bkash' => ['app_key', 'app_secret', 'username', 'password', 'merchant_id', 'webhook_secret'],
            'nagad' => ['merchant_id', 'merchant_number', 'api_key', 'api_secret', 'webhook_secret'],
            'sslcommerz' => ['store_id', 'store_password', 'webhook_secret'],
        ];
    }

    protected function gatewayLabels(): array
    {
        return [
            'bkash' => 'bKash',
            'nagad' => 'Nagad',
            'sslcommerz' => 'SSLCommerz',
        ];
    }

    protected function secretFields(): array
    {
        return ['app_secret', 'password', 'api_secret', 'store_password', 'webhook_secret'];
    }

    public function mount(): void
    {
        $this->fillForm();
    }

    public function fillForm(): void
    {
        $state = [];

        foreach ($this->gatewayFields() as $gateway => $fields) {
            $settings = $this->settingsFor($gateway);

            $state["{$gateway}_enabled"] = $settings?->enabled ?? config("payment-gateways.{$gateway}.enabled", false);
            $state["{$gateway}_sandbox"] = $settings?->sandbox ?? config("payment-gateways.{$gateway}.sandbox", true);

            foreach ($fields as $field) {
                $key = "{$gateway}_{$field}";
                $state[$key] = $settings?->credentials[$field] ?? config("payment-gateways.{$gateway}.{$field}", '');
            }
        }

        $this->form->fill($state);
    }

    public function form(Schema $form): Schema
    {
        $sections = [];

        foreach ($this->gatewayFields() as $gateway => $fields) {
            $components = [
                Grid::make(2)->schema([
                    Toggle::make("{$gateway}_enabled")
                        ->label("Enable {$this->gatewayLabels()[$gateway]}")
                        ->helperText("Turn {$this->gatewayLabels()[$gateway]} payments on/off for your customers."),
                    Toggle::make("{$gateway}_sandbox")
                        ->label('Sandbox mode')
                        ->default(true)
                        ->helperText('Sandbox credentials are used for testing; disable for live payments.'),
                ]),
            ];

            foreach ($fields as $field) {
                $label = $this->credentialLabel($gateway, $field);
                $input = TextInput::make("{$gateway}_{$field}")
                    ->label($label)
                    ->placeholder('Leave blank to keep the current value');

                if (in_array($field, $this->secretFields(), true)) {
                    $input->password()->revealable();
                } else {
                    $input->autocomplete(false);
                }

                $components[] = $input;
            }

            $sections[] = Section::make("{$this->gatewayLabels()[$gateway]} Configuration")
                ->description($this->gatewayDescription($gateway))
                ->schema($components)
                ->collapsible();
        }

        return $form
            ->schema($sections)
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($this->gatewayFields() as $gateway => $fields) {
            $existing = $this->settingsFor($gateway);

            $credentials = $existing?->credentials ?? [];

            foreach ($fields as $field) {
                if (filled($data["{$gateway}_{$field}"] ?? null)) {
                    $credentials[$field] = $data["{$gateway}_{$field}"];
                }
            }

            $settings = PaymentGatewaySetting::firstOrNew([
                'gateway' => $gateway,
                'tenant_id' => null,
            ]);

            $settings->enabled = (bool) ($data["{$gateway}_enabled"] ?? false);
            $settings->sandbox = (bool) ($data["{$gateway}_sandbox"] ?? true);
            $settings->credentials = $credentials;
            $settings->save();

            activity()
                ->by(auth()->user())
                ->on($settings)
                ->withProperties([
                    'enabled' => $settings->enabled,
                    'sandbox' => $settings->sandbox,
                    'credential_fields' => array_keys($credentials),
                ])
                ->log("Payment gateway '{$gateway}' configuration updated");
        }

        Notification::make()
            ->title('Payment gateway settings saved.')
            ->success()
            ->send();
    }

    protected function settingsFor(string $gateway): ?PaymentGatewaySetting
    {
        return PaymentGatewaySetting::query()
            ->where('gateway', $gateway)
            ->orderByRaw('tenant_id IS NULL ASC')
            ->first();
    }

    protected function credentialLabel(string $gateway, string $field): string
    {
        $labels = [
            'app_key' => 'App Key',
            'app_secret' => 'App Secret',
            'username' => 'Username',
            'password' => 'Password',
            'merchant_id' => 'Merchant ID',
            'merchant_number' => 'Merchant Number',
            'api_key' => 'API Key',
            'api_secret' => 'API Secret',
            'store_id' => 'Store ID',
            'store_password' => 'Store Password',
            'webhook_secret' => 'Webhook Secret',
        ];

        return $labels[$field] ?? ucwords(str_replace('_', ' ', $field));
    }

    protected function gatewayDescription(string $gateway): string
    {
        return match ($gateway) {
            'bkash' => 'bKash Tokenized API credentials. Get them from the bKash developer portal.',
            'nagad' => 'Nagad API credentials. Get them from the Nagad merchant portal.',
            'sslcommerz' => 'SSLCommerz Store credentials. Get them from the SSLCommerz merchant panel.',
            default => '',
        };
    }
}
