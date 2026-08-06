<?php

namespace App\Models;

use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wallet transaction model for tracking all prepaid wallet movements.
 * Every credit/debit to customer wallet is logged here for audit purposes.
 * All amounts are in poysha (BDT x 100).
 */
class WalletTransaction extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'customer_id',
        'subscription_id',
        'payment_id',
        'invoice_id',
        'reference_type',
        'reference_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'gateway_reference',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'customer_id' => 'integer',
        'subscription_id' => 'integer',
        'payment_id' => 'integer',
        'invoice_id' => 'integer',
        'reference_id' => 'integer',
        'type' => WalletTransactionType::class,
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'created_by' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (WalletTransaction $transaction) {
            $transaction->uuid = $transaction->uuid ?? (string) \Str::orderedUuid();

            // Calculate balance_after if not set
            if (!isset($transaction->balance_after)) {
                $customer = $transaction->customer;
                if ($customer) {
                    $transaction->balance_after = $transaction->balance_before +
                        ($transaction->type === WalletTransactionType::CREDIT ? $transaction->amount : -$transaction->amount);
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Record a credit transaction (adding to wallet).
     */
    public static function recordCredit(
        Customer $customer,
        int $amount,
        string $description,
        string $referenceType = null,
        int $referenceId = null,
        int $createdBy = null,
        array $metadata = []
    ): self {
        $currentBalance = $customer->wallet_balance;

        $transaction = static::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'type' => WalletTransactionType::CREDIT,
            'amount' => $amount,
            'balance_before' => $currentBalance,
            'balance_after' => $currentBalance + $amount,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $createdBy,
            'metadata' => $metadata,
        ]);

        return $transaction;
    }

    /**
     * Record a debit transaction (deducting from wallet).
     */
    public static function recordDebit(
        Customer $customer,
        int $amount,
        string $description,
        string $referenceType = null,
        int $referenceId = null,
        int $createdBy = null,
        array $metadata = []
    ): self {
        $currentBalance = $customer->wallet_balance;

        $transaction = static::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'type' => WalletTransactionType::DEBIT,
            'amount' => $amount,
            'balance_before' => $currentBalance,
            'balance_after' => $currentBalance - $amount,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $createdBy,
            'metadata' => $metadata,
        ]);

        return $transaction;
    }

    /**
     * Get the current wallet balance for a customer based on transactions.
     */
    public static function getCalculatedBalance(Customer $customer): int
    {
        $credits = static::query()
            ->where('customer_id', $customer->id)
            ->where('type', WalletTransactionType::CREDIT)
            ->sum('amount');

        $debits = static::query()
            ->where('customer_id', $customer->id)
            ->where('type', WalletTransactionType::DEBIT)
            ->sum('amount');

        return $credits - $debits;
    }

    /**
     * Get recent transactions for a customer.
     */
    public static function getRecentForCustomer(Customer $customer, int $limit = 10)
    {
        return static::query()
            ->where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByType($query, WalletTransactionType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByReference($query, string $referenceType, int $referenceId)
    {
        return $query->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId);
    }
}
