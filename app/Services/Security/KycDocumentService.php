<?php

namespace App\Services\Security;

use App\Models\Customer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Encryption\Encrypter;

/**
 * Service for handling KYC document storage and retrieval with encryption.
 * This service ensures that KYC documents are stored securely and only accessible
 * to authorized users through encrypted URLs.
 */
class KycDocumentService
{
    /**
     * The encryption key for document paths.
     */
    protected string $encryptionKey;

    /**
     * The storage disk for KYC documents.
     */
    protected string $storageDisk = 'encrypted';

    public function __construct()
    {
        $this->encryptionKey = config('app.key');
    }

    /**
     * Store a KYC document with encryption.
     */
    public function storeDocument($file, string $documentType, Customer $customer): string
    {
        // Generate a unique filename
        $filename = $this->generateSecureFilename($customer, $documentType);
        
        // Store the file in the encrypted disk
        $path = Storage::disk($this->storageDisk)->putFileAs(
            'kyc/' . $customer->uuid,
            $file,
            $filename
        );

        // Encrypt the storage path before saving to database
        return $this->encryptPath($path);
    }

    /**
     * Retrieve a KYC document path after decryption.
     */
    public function getDocumentPath(string $encryptedPath): ?string
    {
        try {
            $decryptedPath = $this->decryptPath($encryptedPath);
            
            // Verify the path exists and is within the KYC directory
            if (Str::startsWith($decryptedPath, 'kyc/') && Storage::disk($this->storageDisk)->exists($decryptedPath)) {
                return $decryptedPath;
            }
            
            return null;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to decrypt KYC document path', [
                'error' => $e->getMessage(),
                'encrypted_path' => $encryptedPath,
            ]);
            
            return null;
        }
    }

    /**
     * Generate a secure filename for KYC documents.
     */
    protected function generateSecureFilename(Customer $customer, string $documentType): string
    {
        $timestamp = now()->format('YmdHis');
        $random = Str::random(16);
        $customerUuid = Str::replace('-', '', $customer->uuid);
        
        return "{$timestamp}_{$customerUuid}_{$documentType}_{$random}";
    }

    /**
     * Encrypt a storage path.
     */
    public function encryptPath(string $path): string
    {
        $encrypter = new Encrypter(
            substr(hash('sha256', $this->encryptionKey), 0, 32),
            config('app.cipher', 'AES-256-CBC')
        );

        return base64_encode($encrypter->encrypt($path));
    }

    /**
     * Decrypt a storage path.
     */
    public function decryptPath(string $encryptedPath): string
    {
        $encrypter = new Encrypter(
            substr(hash('sha256', $this->encryptionKey), 0, 32),
            config('app.cipher', 'AES-256-CBC')
        );

        return $encrypter->decrypt(base64_decode($encryptedPath));
    }

    /**
     * Generate a time-limited, signed URL for KYC document access.
     * This URL can only be used by authorized users and expires after the TTL.
     */
    public function generateSignedDocumentUrl(string $encryptedPath, int $ttlMinutes = 15): string
    {
        $decryptedPath = $this->decryptPath($encryptedPath);
        
        return Storage::disk($this->storageDisk)->temporaryUrl(
            $decryptedPath,
            now()->addMinutes($ttlMinutes),
            [
                'ResponseContentDisposition' => 'inline',
            ]
        );
    }

    /**
     * Delete a KYC document.
     */
    public function deleteDocument(string $encryptedPath): bool
    {
        try {
            $decryptedPath = $this->decryptPath($encryptedPath);
            
            if (Storage::disk($this->storageDisk)->exists($decryptedPath)) {
                return Storage::disk($this->storageDisk)->delete($decryptedPath);
            }
            
            return false;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to delete KYC document', [
                'error' => $e->getMessage(),
                'encrypted_path' => $encryptedPath,
            ]);
            
            return false;
        }
    }

    /**
     * Validate that a user can access a specific KYC document.
     */
    public function validateAccess($user, Customer $customer): bool
    {
        // Super admins and admins can access any KYC
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // Staff roles with appropriate permissions
        if ($user->hasRole(['billing_agent', 'support_agent', 'noc_engineer'])) {
            // Check if the customer belongs to the same tenant/reseller
            return $user->tenant_id === $customer->tenant_id;
        }

        // Resellers can only access their own customers' KYC
        if ($user->hasRole('reseller')) {
            return $customer->created_by === $user->id;
        }

        // Field technicians and customers cannot access KYC documents
        return false;
    }
}
