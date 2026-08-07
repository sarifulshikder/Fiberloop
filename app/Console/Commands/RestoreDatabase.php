<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Artisan command to restore database from encrypted backups.
 */
class RestoreDatabase extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:restore 
        {file : The backup file to restore from}
        {--connection=pgsql : The database connection to restore to}
        {--test : Restore to a test database instead of production}
        {--drop : Drop the database before restoring}
        {--decrypt : Decrypt the backup file before restoring}
        {--decompress : Decompress the backup file before restoring}
        {--force : Skip confirmation prompts}
        {--timeout=1800 : Restore timeout in seconds}';

    /**
     * The console command description.
     */
    protected $description = 'Restore database from a backup file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $file = $this->argument('file');
        $connection = $this->option('connection') ?: config('database.default', 'pgsql');
        $testMode = $this->option('test');
        $shouldDrop = $this->option('drop');
        $decrypt = $this->option('decrypt');
        $decompress = $this->option('decompress');
        $force = $this->option('force');
        $timeout = (int) $this->option('timeout');

        // Check if file exists
        if (!file_exists($file) && !Storage::disk('local')->exists($file)) {
            $this->error("Backup file not found: {$file}");
            return 1;
        }

        // Resolve full path
        $filePath = $this->resolveFilePath($file);

        if (!file_exists($filePath)) {
            $this->error("Cannot access backup file: {$filePath}");
            return 1;
        }

        $this->info("Starting database restore from: {$filePath}");

        // Determine target database
        $targetDatabase = $this->determineTargetDatabase($connection, $testMode);

        // Check if we need to decrypt
        if ($decrypt || $this->isEncrypted($filePath)) {
            $filePath = $this->handleDecryption($filePath);
        }

        // Check if we need to decompress
        if ($decompress || $this->isCompressed($filePath)) {
            $filePath = $this->handleDecompression($filePath);
        }

        // Get database configuration
        $config = config("database.connections.{$connection}");

        if (!$config) {
            $this->error("Database connection '{$connection}' not found");
            return 1;
        }

        // Confirm before proceeding (unless force mode)
        if (!$force && !$this->confirm(
            "This will RESTORE the database '{$targetDatabase}'. All existing data will be REPLACED! Do you want to continue?",
            true
        )) {
            $this->info("Restore cancelled by user");
            return 0;
        }

        try {
            // Drop database if requested
            if ($shouldDrop) {
                $this->dropDatabase($targetDatabase, $config);
            }

            // Create database if it doesn't exist
            $this->createDatabase($targetDatabase, $config);

            // Restore from backup
            $this->info("Restoring database...");
            $startTime = time();

            $process = $this->createRestoreProcess($filePath, $targetDatabase, $config);
            $process->setTimeout($timeout);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->error("Restore failed!");
                $this->error($process->getErrorOutput());
                return 1;
            }

            $duration = time() - $startTime;
            $this->info("Database restore completed successfully in {$duration} seconds");

            // Verify the restore
            $this->verifyRestore($targetDatabase, $config);

            return 0;

        } catch (\Exception $e) {
            $this->error("Restore failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Resolve the file path.
     */
    protected function resolveFilePath(string $file): string
    {
        if (Storage::disk('local')->exists($file)) {
            return Storage::disk('local')->path($file);
        }

        if (file_exists($file)) {
            return realpath($file);
        }

        // Try common backup locations
        $possiblePaths = [
            storage_path("app/backups/{$file}"),
            storage_path("app/{$file}"),
            base_path("{$file}"),
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return realpath($path);
            }
        }

        return $file;
    }

    /**
     * Determine the target database.
     */
    protected function determineTargetDatabase(string $connection, bool $testMode): string
    {
        if ($testMode) {
            return config("database.connections.{$connection}.database") . '_restored_' . Carbon::now()->format('Ymd_His');
        }

        return config("database.connections.{$connection}.database");
    }

    /**
     * Check if a file is encrypted.
     */
    protected function isEncrypted(string $filePath): bool
    {
        // Check file extension
        if (str_ends_with($filePath, '.enc')) {
            return true;
        }

        // Try to read the file and see if it looks encrypted (base64)
        $content = file_get_contents($filePath, false, null, 0, 100);

        if ($content === false) {
            return false;
        }

        // Base64 encoded content typically starts with these characters
        return preg_match('/^[A-Za-z0-9\/+=]+$/', trim($content)) === 1;
    }

    /**
     * Check if a file is compressed.
     */
    protected function isCompressed(string $filePath): bool
    {
        return str_ends_with($filePath, ['.gz', '.gzip']);
    }

    /**
     * Decrypt a backup file.
     */
    protected function handleDecryption(string $filePath): string
    {
        $this->info("Decrypting backup file...");

        $tempFile = tempnam(sys_get_temp_dir(), 'backup_decrypt_');

        try {
            $this->decryptFile($filePath, $tempFile);
            $this->info("Backup file decrypted");
            return $tempFile;
        } catch (\Exception $e) {
            throw new \Exception("Failed to decrypt file: " . $e->getMessage());
        }
    }

    /**
     * Decompress a backup file.
     */
    protected function handleDecompression(string $filePath): string
    {
        $this->info("Decompressing backup file...");

        $tempFile = tempnam(sys_get_temp_dir(), 'backup_decompress_');

        try {
            $this->decompressFile($filePath, $tempFile);
            $this->info("Backup file decompressed");
            return $tempFile;
        } catch (\Exception $e) {
            throw new \Exception("Failed to decompress file: " . $e->getMessage());
        }
    }

    /**
     * Drop a database.
     */
    protected function dropDatabase(string $database, array $config): void
    {
        $this->info("Dropping database: {$database}");

        $process = new Process([
            'dropdb',
            '--host', $config['host'] ?? 'localhost',
            '--port', $config['port'] ?? 5432,
            '--username', $config['username'] ?? 'postgres',
            '--dbname', $database,
            '--force',
        ], null, ['PGPASSWORD' => $config['password'] ?? '']);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \Exception("Failed to drop database: " . $process->getErrorOutput());
        }

        $this->info("Database dropped successfully");
    }

    /**
     * Create a database.
     */
    protected function createDatabase(string $database, array $config): void
    {
        $this->info("Creating database: {$database}");

        $process = new Process([
            'createdb',
            '--host', $config['host'] ?? 'localhost',
            '--port', $config['port'] ?? 5432,
            '--username', $config['username'] ?? 'postgres',
            '--dbname', $database,
        ], null, ['PGPASSWORD' => $config['password'] ?? '']);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \Exception("Failed to create database: " . $process->getErrorOutput());
        }

        $this->info("Database created successfully");
    }

    /**
     * Create the restore process.
     */
    protected function createRestoreProcess(string $filePath, string $database, array $config): Process
    {
        return new Process([
            'psql',
            '--host', $config['host'] ?? 'localhost',
            '--port', $config['port'] ?? 5432,
            '--username', $config['username'] ?? 'postgres',
            '--dbname', $database,
            '--file', $filePath,
            '--no-password',
            '--quiet',
        ], null, ['PGPASSWORD' => $config['password'] ?? '']);
    }

    /**
     * Verify the restore was successful.
     */
    protected function verifyRestore(string $database, array $config): void
    {
        $this->info("Verifying restore...");

        $process = new Process([
            'psql',
            '--host', $config['host'] ?? 'localhost',
            '--port', $config['port'] ?? 5432,
            '--username', $config['username'] ?? 'postgres',
            '--dbname', $database,
            '--command', '\dt',
            '--tuples-only',
        ], null, ['PGPASSWORD' => $config['password'] ?? '']);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \Exception("Failed to verify restore: " . $process->getErrorOutput());
        }

        $tableCount = count(explode("\n", trim($process->getOutput())));
        $this->info("Restore verification successful: {$tableCount} tables found in database '{$database}'");
    }

    /**
     * Decrypt a file using Laravel's encryption.
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
     * Decompress a gzip file.
     */
    protected function decompressFile(string $source, string $destination): void
    {
        $process = new Process(['gunzip', '--keep', '--force', $source]);
        $process->run();

        if ($process->isSuccessful()) {
            $decompressedFile = substr($source, 0, -3); // Remove .gz extension
            if (file_exists($decompressedFile)) {
                rename($decompressedFile, $destination);
            }
        } else {
            throw new \Exception("Failed to decompress file: " . $process->getErrorOutput());
        }
    }
}
