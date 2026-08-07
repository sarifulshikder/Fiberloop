<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Artisan command to create encrypted database backups.
 * This command creates PostgreSQL dump files, encrypts them,
 * and stores them securely with optional cloud storage.
 */
class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:backup 
        {--connection=pgsql : The database connection to backup}
        {--filename= : Custom filename for the backup}
        {--encrypt : Encrypt the backup file}
        {--cloud : Upload to cloud storage}
        {--test-restore : Test the restore procedure}
        {--path= : Custom backup path}';

    /**
     * The console command description.
     */
    protected $description = 'Create an encrypted database backup';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = $this->option('connection') ?: config('database.default', 'pgsql');
        $encrypt = $this->option('encrypt') || app()->environment('production');
        $cloud = $this->option('cloud');
        $testRestore = $this->option('test-restore');
        $customPath = $this->option('path');
        $customFilename = $this->option('filename');

        // Generate backup filename
        $timestamp = now()->format('Ymd_His');
        $filename = $customFilename ?: "backup_{$connection}_{$timestamp}.sql";

        // Get database configuration
        $config = config("database.connections.{$connection}");

        if (!$config) {
            $this->error("Database connection '{$connection}' not found");
            return 1;
        }

        $this->info("Starting database backup for connection: {$connection}");

        // Create backup directory
        $backupPath = $customPath ?: storage_path('app/backups');

        if (!Storage::disk('local')->exists('backups')) {
            Storage::disk('local')->makeDirectory('backups');
        }

        $backupFile = "{$backupPath}/{$filename}";
        $finalBackupFile = $backupFile;

        try {
            // Create the database dump
            $this->info("Creating database dump...");

            $process = $this->createDumpProcess($config, $backupFile);
            $process->setTimeout(3600); // 1 hour timeout
            $process->run();

            if (!$process->isSuccessful()) {
                $this->error("Failed to create database dump");
                $this->error($process->getErrorOutput());
                return 1;
            }

            $this->info("Database dump created successfully");

            // Compress the dump
            $compressedFile = "{$backupFile}.gz";
            $this->compressFile($backupFile, $compressedFile);
            Storage::disk('local')->delete($backupFile);
            $finalBackupFile = $compressedFile;

            $this->info("Database dump compressed");

            // Encrypt if requested
            if ($encrypt) {
                $encryptedFile = "{$compressedFile}.enc";
                $this->encryptFile($compressedFile, $encryptedFile);
                Storage::disk('local')->delete($compressedFile);
                $finalBackupFile = $encryptedFile;

                $this->info("Database dump encrypted");
            }

            // Upload to cloud if requested
            if ($cloud) {
                $this->uploadToCloud($finalBackupFile);
            }

            // Test restore if requested
            if ($testRestore) {
                $this->testRestore($finalBackupFile, $encrypt);
            }

            $this->info("Backup completed successfully: {$finalBackupFile}");

            // Output backup information
            $this->line("Backup file: " . realpath($finalBackupFile));
            $this->line("File size: " . $this->formatBytes(filesize($finalBackupFile)));
            $this->line("Encrypted: " . ($encrypt ? 'Yes' : 'No'));
            $this->line("Compressed: Yes");

            return 0;

        } catch (\Exception $e) {
            $this->error("Backup failed: " . $e->getMessage());

            // Clean up partial files
            if (Storage::disk('local')->exists($backupFile)) {
                Storage::disk('local')->delete($backupFile);
            }
            if (Storage::disk('local')->exists($compressedFile ?? '')) {
                Storage::disk('local')->delete($compressedFile);
            }
            if (Storage::disk('local')->exists($encryptedFile ?? '')) {
                Storage::disk('local')->delete($encryptedFile);
            }

            return 1;
        }
    }

    /**
     * Create the database dump process.
     */
    protected function createDumpProcess(array $config, string $backupFile): Process
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 5432;
        $database = $config['database'] ?? 'fiberloop';
        $username = $config['username'] ?? 'postgres';
        $password = $config['password'] ?? '';

        // Build pg_dump command
        $command = [
            'pg_dump',
            '--host', $host,
            '--port', $port,
            '--username', $username,
            '--dbname', $database,
            '--format', 'plain',
            '--no-owner',
            '--no-privileges',
            '--file', $backupFile,
        ];

        // Add password via environment variable to avoid command line exposure
        $_ENV['PGPASSWORD'] = $password;

        return new Process($command, null, ['PGPASSWORD' => $password]);
    }

    /**
     * Compress a file using gzip.
     */
    protected function compressFile(string $source, string $destination): void
    {
        $process = new Process(['gzip', '--keep', '--force', $source]);
        $process->run();

        if ($process->isSuccessful()) {
            rename($source . '.gz', $destination);
        } else {
            throw new \Exception("Failed to compress file: " . $process->getErrorOutput());
        }
    }

    /**
     * Encrypt a file using Laravel's encryption.
     */
    protected function encryptFile(string $source, string $destination): void
    {
        $key = substr(hash('sha256', config('app.key')), 0, 32);
        $cipher = config('app.cipher', 'AES-256-CBC');
        $encrypter = new Encrypter($key, $cipher);

        $content = file_get_contents($source);
        $encryptedContent = $encrypter->encrypt($content);

        file_put_contents($destination, base64_encode($encryptedContent));
    }

    /**
     * Decrypt a file.
     */
    protected function decryptFile(string $source, string $destination): void
    {
        $key = substr(hash('sha256', config('app.key')), 0, 32);
        $cipher = config('app.cipher', 'AES-256-CBC');
        $encrypter = new Encrypter($key, $cipher);

        $encryptedContent = base64_decode(file_get_contents($source));
        $decryptedContent = $encrypter->decrypt($encryptedContent);

        file_put_contents($destination, $decryptedContent);
    }

    /**
     * Upload backup to cloud storage.
     */
    protected function uploadToCloud(string $filePath): void
    {
        $disk = config('backup.cloud_disk', 's3');
        $bucket = config('backup.cloud_bucket', 'backups');
        $filename = basename($filePath);
        $cloudPath = "backups/" . now()->format('Y/m/d') . "/" . $filename;

        if (Storage::disk($disk)->put($cloudPath, file_get_contents($filePath))) {
            $this->info("Backup uploaded to cloud storage: {$cloudPath}");
        } else {
            $this->error("Failed to upload backup to cloud storage");
        }
    }

    /**
     * Test the restore procedure.
     */
    protected function testRestore(string $backupFile, bool $encrypted): void
    {
        $this->info("Testing backup restore procedure...");

        $testDatabase = 'fiberloop_test_restore_' . Str::random(8);
        $tempFile = tempnam(sys_get_temp_dir(), 'backup_test_');

        try {
            // Decrypt if encrypted
            if ($encrypted) {
                $this->decryptFile($backupFile, $tempFile);
                $backupFile = $tempFile;
            }

            // Decompress if needed
            if (Str::endsWith($backupFile, '.gz')) {
                $decompressedFile = tempnam(sys_get_temp_dir(), 'backup_decompressed_');
                $this->decompressFile($backupFile, $decompressedFile);
                $backupFile = $decompressedFile;
            }

            // Restore to test database
            $process = $this->createRestoreProcess($backupFile, $testDatabase);
            $process->setTimeout(1800); // 30 minutes timeout
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \Exception("Restore failed: " . $process->getErrorOutput());
            }

            $this->info("Backup restore test successful");
            $this->info("Test database: {$testDatabase}");

            // Verify the restore by checking if tables exist
            $this->verifyRestore($testDatabase);

            // Clean up test database
            $this->dropDatabase($testDatabase);

        } catch (\Exception $e) {
            $this->error("Restore test failed: " . $e->getMessage());
            throw $e;
        } finally {
            // Clean up temp files
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            if (isset($decompressedFile) && file_exists($decompressedFile)) {
                unlink($decompressedFile);
            }
        }
    }

    /**
     * Create the database restore process.
     */
    protected function createRestoreProcess(string $backupFile, string $database): Process
    {
        $config = config('database.connections.pgsql');
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 5432;
        $username = $config['username'] ?? 'postgres';
        $password = $config['password'] ?? '';

        // First, create the test database
        $createDbProcess = new Process([
            'createdb',
            '--host', $host,
            '--port', $port,
            '--username', $username,
            '--dbname', $database,
        ], null, ['PGPASSWORD' => $password]);
        $createDbProcess->run();

        if (!$createDbProcess->isSuccessful()) {
            throw new \Exception("Failed to create test database: " . $createDbProcess->getErrorOutput());
        }

        // Restore the backup
        $restoreProcess = new Process([
            'psql',
            '--host', $host,
            '--port', $port,
            '--username', $username,
            '--dbname', $database,
            '--file', $backupFile,
        ], null, ['PGPASSWORD' => $password]);

        return $restoreProcess;
    }

    /**
     * Decompress a gzip file.
     */
    protected function decompressFile(string $source, string $destination): void
    {
        $process = new Process(['gunzip', '--keep', '--force', $source]);
        $process->run();

        if ($process->isSuccessful()) {
            rename(substr($source, 0, -3), $destination);
        } else {
            throw new \Exception("Failed to decompress file: " . $process->getErrorOutput());
        }
    }

    /**
     * Verify that the restore was successful.
     */
    protected function verifyRestore(string $database): void
    {
        $config = config('database.connections.pgsql');
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 5432;
        $username = $config['username'] ?? 'postgres';
        $password = $config['password'] ?? '';

        // List tables in the test database
        $process = new Process([
            'psql',
            '--host', $host,
            '--port', $port,
            '--username', $username,
            '--dbname', $database,
            '--command', '\\dt',
        ], null, ['PGPASSWORD' => $password]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \Exception("Failed to verify restore: " . $process->getErrorOutput());
        }

        $output = $process->getOutput();
        $tableCount = substr_count($output, 'public.');

        $this->info("Restore verification successful: {$tableCount} tables found");
    }

    /**
     * Drop a database.
     */
    protected function dropDatabase(string $database): void
    {
        $config = config('database.connections.pgsql');
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 5432;
        $username = $config['username'] ?? 'postgres';
        $password = $config['password'] ?? '';

        $process = new Process([
            'dropdb',
            '--host', $host,
            '--port', $port,
            '--username', $username,
            '--dbname', $database,
        ], null, ['PGPASSWORD' => $password]);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error("Failed to drop test database: " . $process->getErrorOutput());
        }
    }

    /**
     * Format bytes to human-readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
