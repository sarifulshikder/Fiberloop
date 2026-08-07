<?php

namespace App\Services\Alerting;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SystemAlertNotification;

/**
 * Centralized alert management service for critical system events.
 * 
 * This service handles alert routing, severity levels, throttling, and escalation
 * policies to ensure that critical issues are properly communicated to the
 * appropriate teams without causing alert fatigue.
 */
class AlertManager
{
    /**
     * Alert severity levels.
     */
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_INFO = 'info';

    /**
     * Alert categories.
     */
    public const CATEGORY_RADIUS = 'radius';
    public const CATEGORY_DATABASE = 'database';
    public const CATEGORY_APPLICATION = 'application';
    public const CATEGORY_QUEUE = 'queue';
    public const CATEGORY_PAYMENT = 'payment';
    public const CATEGORY_BILLING = 'billing';
    public const CATEGORY_MONITORING = 'monitoring';
    public const CATEGORY_SECURITY = 'security';
    public const CATEGORY_INFRASTRUCTURE = 'infrastructure';

    /**
     * Maximum alerts per component within a time window (to prevent spam).
     */
    protected int $throttleWindow = 3600; // 1 hour in seconds
    protected int $maxAlertsPerComponent = 3;

    /**
     * Track throttled alerts.
     */
    protected array $throttleCache = [];

    /**
     * Escalation matrix: severity -> notification channels.
     */
    protected array $escalationMatrix = [
        self::SEVERITY_CRITICAL => [
            'slack',
            'sms',
            'email',
            'pagerduty',
        ],
        self::SEVERITY_HIGH => [
            'slack',
            'email',
            'pagerduty',
        ],
        self::SEVERITY_MEDIUM => [
            'slack',
            'email',
        ],
        self::SEVERITY_LOW => [
            'slack',
        ],
        self::SEVERITY_INFO => [
            'log',
        ],
    ];

    /**
     * Component-specific notification routing.
     */
    protected array $componentRouting = [
        self::CATEGORY_RADIUS => [
            'slack' => '#noc-alerts',
            'sms' => '+1234567890', // NOC team lead
            'email' => 'noc@fiberloop.com',
        ],
        self::CATEGORY_DATABASE => [
            'slack' => '#db-alerts',
            'sms' => '+1234567890', // DBA team
            'email' => 'dba@fiberloop.com',
        ],
        self::CATEGORY_PAYMENT => [
            'slack' => '#payments-alerts',
            'sms' => '+1234567890', // Billing manager
            'email' => 'billing@fiberloop.com',
        ],
        self::CATEGORY_BILLING => [
            'slack' => '#billing-alerts',
            'sms' => '+1234567890', // Billing manager
            'email' => 'billing@fiberloop.com',
        ],
        self::CATEGORY_APPLICATION => [
            'slack' => '#app-alerts',
            'sms' => '+1234567890', // Dev team lead
            'email' => 'dev@fiberloop.com',
        ],
        self::CATEGORY_QUEUE => [
            'slack' => '#ops-alerts',
            'email' => 'ops@fiberloop.com',
        ],
        self::CATEGORY_MONITORING => [
            'slack' => '#monitoring-alerts',
            'email' => 'monitoring@fiberloop.com',
        ],
        self::CATEGORY_SECURITY => [
            'slack' => '#security-alerts',
            'sms' => '+1234567890', // Security team
            'email' => 'security@fiberloop.com',
            'pagerduty' => true,
        ],
        self::CATEGORY_INFRASTRUCTURE => [
            'slack' => '#infra-alerts',
            'sms' => '+1234567890', // Infra team
            'email' => 'infra@fiberloop.com',
        ],
    ];

    /**
     * On-call rotation (for PagerDuty/SMS escalation).
     */
    protected array $onCallRotation = [
        'noc' => '1234567890',
        'dba' => '2345678901',
        'dev' => '3456789012',
        'security' => '4567890123',
        'primary' => '5678901234',
    ];

    /**
     * Send an alert.
     */
    public function sendAlert(
        string $title,
        string $message,
        string $severity = self::SEVERITY_INFO,
        string $category = self::CATEGORY_APPLICATION,
        array $context = [],
        bool $force = false
    ): array {
        $alertId = $this->generateAlertId($title, $category);
        
        // Check throttling
        if (!$force && $this->isThrottled($category, $alertId)) {
            return [
                'sent' => false,
                'reason' => 'throttled',
                'alert_id' => $alertId,
            ];
        }

        // Record this alert for throttling
        $this->recordAlert($category, $alertId);

        // Get notification channels based on severity
        $channels = $this->escalationMatrix[$severity] ?? [];

        // Get component-specific routing
        $routing = $this->componentRouting[$category] ?? $this->componentRouting[self::CATEGORY_APPLICATION];

        $results = [];
        $alertData = [
            'id' => $alertId,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'category' => $category,
            'timestamp' => Carbon::now()->toISOString(),
            'context' => $context,
        ];

        // Log the alert regardless
        $this->logAlert($alertData);
        $results['log'] = true;

        // Send to each channel
        foreach ($channels as $channel) {
            try {
                $sent = $this->sendToChannel($channel, $alertData, $routing);
                $results[$channel] = $sent;
            } catch (\Exception $e) {
                Log::error("Failed to send alert to channel: $channel", [
                    'alert_id' => $alertId,
                    'error' => $e->getMessage(),
                ]);
                $results[$channel] = false;
            }
        }

        return [
            'sent' => true,
            'alert_id' => $alertId,
            'results' => $results,
        ];
    }

    /**
     * Send alert to a specific channel.
     */
    protected function sendToChannel(string $channel, array $alertData, array $routing): bool
    {
        switch ($channel) {
            case 'slack':
                return $this->sendToSlack($alertData, $routing);

            case 'sms':
                return $this->sendToSms($alertData, $routing);

            case 'email':
                return $this->sendToEmail($alertData, $routing);

            case 'pagerduty':
                return $this->sendToPagerDuty($alertData, $routing);

            case 'log':
                return true; // Already logged

            default:
                return false;
        }
    }

    /**
     * Send alert to Slack.
     */
    protected function sendToSlack(array $alertData, array $routing): bool
    {
        $webhookUrl = config('services.slack.webhook_url');
        
        if (!$webhookUrl) {
            Log::warning('Slack webhook URL not configured');
            return false;
        }

        $channel = $routing['slack'] ?? config('services.slack.channel', '#general');

        $color = $this->getSlackColor($alertData['severity']);
        $emoji = $this->getSlackEmoji($alertData['severity']);

        $payload = [
            'channel' => ltrim($channel, '#'),
            'username' => 'Fiberloop Alert Bot',
            'icon_emoji' => $emoji,
            'attachments' => [[
                'color' => $color,
                'title' => $emoji . ' ' . $alertData['title'],
                'text' => $alertData['message'],
                'fields' => [
                    [
                        'title' => 'Severity',
                        'value' => strtoupper($alertData['severity']),
                        'short' => true,
                    ],
                    [
                        'title' => 'Category',
                        'value' => strtoupper($alertData['category']),
                        'short' => true,
                    ],
                    [
                        'title' => 'Time',
                        'value' => $alertData['timestamp'],
                        'short' => true,
                    ],
                ],
                'footer' => 'Fiberloop Monitoring',
                'ts' => strtotime($alertData['timestamp']),
            ]],
        ];

        if (!empty($alertData['context'])) {
            foreach ($alertData['context'] as $key => $value) {
                $payload['attachments'][0]['fields'][] = [
                    'title' => $key,
                    'value' => is_array($value) ? json_encode($value) : $value,
                    'short' => false,
                ];
            }
        }

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post($webhookUrl, $payload);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Failed to send Slack notification', [
                'error' => $e->getMessage(),
                'alert_id' => $alertData['id'],
            ]);
            return false;
        }
    }

    /**
     * Send alert via SMS.
     */
    protected function sendToSms(array $alertData, array $routing): bool
    {
        $phoneNumber = $routing['sms'] ?? $this->onCallRotation['primary'];
        $message = $this->formatSmsMessage($alertData);

        // Use configured SMS provider
        $provider = config('services.sms.provider', 'twilio');

        try {
            switch ($provider) {
                case 'twilio':
                    return $this->sendTwilioSms($phoneNumber, $message);

                case 'nexmo':
                    return $this->sendNexmoSms($phoneNumber, $message);

                case 'log':
                    Log::info('SMS Alert (mock): ' . $message, [
                        'alert_id' => $alertData['id'],
                        'to' => $phoneNumber,
                    ]);
                    return true;

                default:
                    Log::warning("Unknown SMS provider: $provider");
                    return false;
            }
        } catch (\Exception $e) {
            Log::error('Failed to send SMS alert', [
                'error' => $e->getMessage(),
                'alert_id' => $alertData['id'],
                'to' => $phoneNumber,
            ]);
            return false;
        }
    }

    /**
     * Send alert via Email.
     */
    protected function sendToEmail(array $alertData, array $routing): bool
    {
        $email = $routing['email'] ?? config('mail.from.address');
        $recipients = is_array($email) ? $email : [$email];

        try {
            foreach ($recipients as $recipient) {
                Mail::to($recipient)->send(new SystemAlertNotification($alertData));
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send email alert', [
                'error' => $e->getMessage(),
                'alert_id' => $alertData['id'],
                'to' => $recipients,
            ]);
            return false;
        }
    }

    /**
     * Send alert to PagerDuty.
     */
    protected function sendToPagerDuty(array $alertData, array $routing): bool
    {
        $routingKey = config('services.pagerduty.routing_key');
        
        if (!$routingKey) {
            Log::warning('PagerDuty routing key not configured');
            return false;
        }

        $severityMap = [
            self::SEVERITY_CRITICAL => 'critical',
            self::SEVERITY_HIGH => 'error',
            self::SEVERITY_MEDIUM => 'warning',
            self::SEVERITY_LOW => 'info',
            self::SEVERITY_INFO => 'info',
        ];

        $payload = [
            'routing_key' => $routingKey,
            'event_action' => 'trigger',
            'dedup_key' => $alertData['id'],
            'payload' => [
                'summary' => $alertData['title'],
                'severity' => $severityMap[$alertData['severity']] ?? 'info',
                'source' => 'fiberloop-' . $alertData['category'],
                'custom_details' => [
                    'message' => $alertData['message'],
                    'category' => $alertData['category'],
                    'timestamp' => $alertData['timestamp'],
                    'context' => $alertData['context'],
                ],
            ],
        ];

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post('https://events.pagerduty.com/v2/enqueue', $payload);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Failed to send PagerDuty alert', [
                'error' => $e->getMessage(),
                'alert_id' => $alertData['id'],
            ]);
            return false;
        }
    }

    /**
     * Log the alert.
     */
    protected function logAlert(array $alertData): void
    {
        $logLevel = $this->severityToLogLevel($alertData['severity']);
        
        Log::channel('alerts')->$logLevel($alertData['title'], [
            'alert_id' => $alertData['id'],
            'message' => $alertData['message'],
            'severity' => $alertData['severity'],
            'category' => $alertData['category'],
            'timestamp' => $alertData['timestamp'],
            'context' => $alertData['context'],
        ]);
    }

    /**
     * Check if an alert should be throttled.
     */
    protected function isThrottled(string $component, string $alertId): bool
    {
        $key = "$component:$alertId";
        
        if (!isset($this->throttleCache[$key])) {
            return false;
        }

        $now = time();
        $windowStart = $now - $this->throttleWindow;

        // Remove old entries
        $this->throttleCache[$key] = array_filter(
            $this->throttleCache[$key],
            function ($timestamp) use ($windowStart) {
                return $timestamp > $windowStart;
            }
        );

        return count($this->throttleCache[$key]) >= $this->maxAlertsPerComponent;
    }

    /**
     * Record an alert for throttling.
     */
    protected function recordAlert(string $component, string $alertId): void
    {
        $key = "$component:$alertId";
        
        if (!isset($this->throttleCache[$key])) {
            $this->throttleCache[$key] = [];
        }

        $this->throttleCache[$key][] = time();
    }

    /**
     * Generate a unique alert ID.
     */
    protected function generateAlertId(string $title, string $category): string
    {
        return md5($category . ':' . $title . ':' . date('Y-m-d'));
    }

    /**
     * Get Slack color based on severity.
     */
    protected function getSlackColor(string $severity): string
    {
        return match ($severity) {
            self::SEVERITY_CRITICAL => 'danger',
            self::SEVERITY_HIGH => 'danger',
            self::SEVERITY_MEDIUM => 'warning',
            self::SEVERITY_LOW => 'good',
            self::SEVERITY_INFO => '#439FE0',
            default => '#439FE0',
        };
    }

    /**
     * Get Slack emoji based on severity.
     */
    protected function getSlackEmoji(string $severity): string
    {
        return match ($severity) {
            self::SEVERITY_CRITICAL => ':rotating_light:',
            self::SEVERITY_HIGH => ':fire:',
            self::SEVERITY_MEDIUM => ':warning:',
            self::SEVERITY_LOW => ':information_source:',
            self::SEVERITY_INFO => ':information_source:',
            default => ':information_source:',
        };
    }

    /**
     * Format alert message for SMS.
     */
    protected function formatSmsMessage(array $alertData): string
    {
        $severity = strtoupper($alertData['severity']);
        $timestamp = Carbon::parse($alertData['timestamp'])->format('Y-m-d H:i:s');
        
        return "[$severity] {$alertData['title']}: {$alertData['message']} - $timestamp";
    }

    /**
     * Send SMS via Twilio.
     */
    protected function sendTwilioSms(string $phoneNumber, string $message): bool
    {
        $accountSid = config('services.twilio.sid');
        $authToken = config('services.twilio.token');
        $from = config('services.twilio.from');

        if (!$accountSid || !$authToken || !$from) {
            Log::warning('Twilio credentials not configured');
            return false;
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($accountSid, $authToken)
                ->post("https://api.twilio.com/2010-04-01/Accounts/$accountSid/Messages.json", [
                    'To' => $phoneNumber,
                    'From' => $from,
                    'Body' => $message,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Failed to send Twilio SMS', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send SMS via Nexmo/Vonage.
     */
    protected function sendNexmoSms(string $phoneNumber, string $message): bool
    {
        $apiKey = config('services.nexmo.key');
        $apiSecret = config('services.nexmo.secret');
        $from = config('services.nexmo.from');

        if (!$apiKey || !$apiSecret || !$from) {
            Log::warning('Nexmo credentials not configured');
            return false;
        }

        try {
            $response = Http::withBasicAuth($apiKey, $apiSecret)
                ->post('https://rest.nexmo.com/sms/json', [
                    'to' => $phoneNumber,
                    'from' => $from,
                    'text' => $message,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Failed to send Nexmo SMS', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Convert alert severity to log level.
     */
    protected function severityToLogLevel(string $severity): string
    {
        return match ($severity) {
            self::SEVERITY_CRITICAL => 'critical',
            self::SEVERITY_HIGH => 'error',
            self::SEVERITY_MEDIUM => 'warning',
            self::SEVERITY_LOW => 'notice',
            self::SEVERITY_INFO => 'info',
            default => 'info',
        };
    }

    /**
     * Alert for RADIUS service down.
     */
    public function radiusDown(string $message = 'RADIUS service is down', array $context = []): array
    {
        return $this->sendAlert(
            'RADIUS Service Down',
            $message,
            self::SEVERITY_CRITICAL,
            self::CATEGORY_RADIUS,
            $context
        );
    }

    /**
     * Alert for database down.
     */
    public function databaseDown(string $message = 'Database service is down', array $context = []): array
    {
        return $this->sendAlert(
            'Database Service Down',
            $message,
            self::SEVERITY_CRITICAL,
            self::CATEGORY_DATABASE,
            $context
        );
    }

    /**
     * Alert for billing failure.
     */
    public function billingFailed(string $message = 'Billing run failed', array $context = []): array
    {
        return $this->sendAlert(
            'Billing Failure',
            $message,
            self::SEVERITY_CRITICAL,
            self::CATEGORY_BILLING,
            $context
        );
    }

    /**
     * Alert for payment processing failure.
     */
    public function paymentFailed(string $message = 'Payment processing failed', array $context = []): array
    {
        return $this->sendAlert(
            'Payment Processing Failed',
            $message,
            self::SEVERITY_HIGH,
            self::CATEGORY_PAYMENT,
            $context
        );
    }

    /**
     * Alert for queue failure.
     */
    public function queueFailed(string $message = 'Queue processing failed', array $context = []): array
    {
        return $this->sendAlert(
            'Queue Processing Failed',
            $message,
            self::SEVERITY_HIGH,
            self::CATEGORY_QUEUE,
            $context
        );
    }

    /**
     * Alert for security incident.
     */
    public function securityIncident(string $message = 'Security incident detected', array $context = []): array
    {
        return $this->sendAlert(
            'Security Incident',
            $message,
            self::SEVERITY_CRITICAL,
            self::CATEGORY_SECURITY,
            $context,
            true // Force send, don't throttle security incidents
        );
    }

    /**
     * Acknowledge/resolve an alert.
     */
    public function acknowledgeAlert(string $alertId, string $message = 'Alert acknowledged'): bool
    {
        try {
            // Send resolution to PagerDuty if configured
            if (config('services.pagerduty.routing_key')) {
                $payload = [
                    'routing_key' => config('services.pagerduty.routing_key'),
                    'event_action' => 'acknowledge',
                    'dedup_key' => $alertId,
                ];

                Http::withHeaders(['Content-Type' => 'application/json'])
                    ->post('https://events.pagerduty.com/v2/enqueue', $payload);
            }

            // Log the acknowledgment
            Log::channel('alerts')->info("Alert acknowledged: $alertId", [
                'message' => $message,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to acknowledge alert', [
                'alert_id' => $alertId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
