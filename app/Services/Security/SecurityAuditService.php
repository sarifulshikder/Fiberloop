<?php

namespace App\Services\Security;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ReflectionClass;

/**
 * Service for auditing SQL injection and mass assignment vulnerabilities.
 * This service checks all models for proper fillable/guarded configuration
 * and audits raw SQL queries for parameterization.
 */
class SecurityAuditService
{
    /**
     * Audit all models for mass assignment vulnerabilities.
     * Returns a report of models with potential issues.
     */
    public function auditMassAssignment(): array
    {
        $report = [
            'vulnerable_models' => [],
            'safe_models' => [],
            'total_models_checked' => 0,
        ];

        $modelFiles = glob(app_path('Models/*.php'));

        foreach ($modelFiles as $file) {
            $className = 'App\\Models\\' . basename($file, '.php');

            try {
                if (!class_exists($className)) {
                    continue;
                }

                $reflection = new ReflectionClass($className);

                // Check if class extends Model
                if (!$reflection->isSubclassOf(Model::class)) {
                    continue;
                }

                $model = new $className();
                $report['total_models_checked']++;

                $fillable = $model->getFillable();
                $guarded = $model->getGuarded();

                // Check if model has any fillable fields
                if (empty($fillable) && empty($guarded)) {
                    // No fillable or guarded specified - this is vulnerable
                    $report['vulnerable_models'][] = [
                        'model' => $className,
                        'issue' => 'No $fillable or $guarded specified',
                        'severity' => 'HIGH',
                        'recommendation' => 'Add $fillable array with allowed fields or $guarded = ["*"]',
                    ];
                    continue;
                }

                // Check if model uses guarded with * but also has fillable
                if (in_array('*', $guarded, true) && !empty($fillable)) {
                    $report['vulnerable_models'][] = [
                        'model' => $className,
                        'issue' => 'Both $guarded = ["*"] and $fillable specified - $fillable takes precedence',
                        'severity' => 'MEDIUM',
                        'recommendation' => 'Use either $fillable or $guarded, not both',
                    ];
                    continue;
                }

                // Model is safe
                $report['safe_models'][] = $className;

            } catch (\Exception $e) {
                Log::error("Error auditing model {$className}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $report;
    }

    /**
     * Audit database queries for raw SQL without parameterization.
     * This should be run as a scheduled job or in development.
     */
    public function auditRawQueries(): array
    {
        $report = [
            'raw_queries' => [],
            'parameterized_queries' => [],
            'total_queries' => 0,
        ];

        // This method requires query logging to be enabled
        // In production, this should be done via a dedicated logging channel

        // Get recent queries from the query log
        if (config('database.log_queries', false)) {
            $queries = DB::getQueryLog();

            foreach ($queries as $query) {
                $report['total_queries']++;

                $sql = $query['query'];
                $bindings = $query['bindings'] ?? [];

                // Check if query contains ? placeholders
                if (str_contains($sql, '?') && !empty($bindings)) {
                    $report['parameterized_queries'][] = [
                        'sql' => $sql,
                        'bindings' => $bindings,
                        'time' => $query['time'] ?? null,
                    ];
                    continue;
                }

                // Check for potential raw SQL with concatenated values
                if (preg_match('/\b(WHERE|AND|OR)\s+.*=\s*[\'\"].*[\'\"]/', $sql)) {
                    $report['raw_queries'][] = [
                        'sql' => $sql,
                        'risk' => 'HIGH',
                        'recommendation' => 'Use parameter binding instead of string concatenation',
                    ];
                } elseif (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\s+.*\b/', $sql)) {
                    $report['parameterized_queries'][] = [
                        'sql' => $sql,
                        'bindings' => $bindings,
                        'time' => $query['time'] ?? null,
                    ];
                }
            }
        }

        return $report;
    }

    /**
     * Check for common SQL injection patterns in a query string.
     */
    public function checkQueryForInjection(string $sql): array
    {
        $issues = [];

        // Check for common SQL injection patterns
        $patterns = [
            '/\b(OR\s+1=1|OR\s+\'a\'=\'a|OR\s+\"a\"=\"a)\b/i' => 'Tautology-based SQL injection',
            '/\b(UNION\s+SELECT)\b/i' => 'UNION-based SQL injection',
            '/\b(DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i' => 'Destructive SQL command',
            '/\b(EXEC\s+|EXECUTE\s+)\b/i' => 'SQL execution command',
            '/\b(WAITFOR\s+DELAY|SHUTDOWN)\b/i' => 'Time-based SQL injection',
            '/\b(LOAD_FILE|INTO\s+OUTFILE)\b/i' => 'File system access SQL',
            '/;\s*(SELECT|INSERT|UPDATE|DELETE)/i' => 'Query stacking',
        ];

        foreach ($patterns as $pattern => $description) {
            if (preg_match($pattern, $sql)) {
                $issues[] = [
                    'pattern' => $pattern,
                    'description' => $description,
                    'severity' => 'CRITICAL',
                ];
            }
        }

        return [
            'sql' => $sql,
            'is_safe' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Get a list of models that need security review.
     */
    public function getModelsNeedingReview(): array
    {
        $modelsNeedingReview = [];
        $modelFiles = glob(app_path('Models/*.php'));

        foreach ($modelFiles as $file) {
            $className = 'App\\Models\\' . basename($file, '.php');

            try {
                if (!class_exists($className)) {
                    continue;
                }

                $reflection = new ReflectionClass($className);

                // Check if class extends Model
                if (!$reflection->isSubclassOf(Model::class)) {
                    continue;
                }

                $model = new $className();
                $table = $model->getTable();

                // Models with sensitive data should be reviewed
                $sensitiveColumns = [
                    'password', 'secret', 'token', 'api_key', 'api_secret',
                    'private_key', 'certificate', 'nid', 'ssn', 'kyc',
                    'credit_card', 'bank_account', 'routing_number',
                ];

                $modelFillable = $model->getFillable();
                $modelGuarded = $model->getGuarded();

                // Check if model has sensitive columns
                foreach ($sensitiveColumns as $column) {
                    if (in_array($column, $modelFillable, true) ||
                        !in_array($column, $modelGuarded, true) &&
                        !in_array('*', $modelGuarded, true)) {
                        $modelsNeedingReview[] = [
                            'model' => $className,
                            'table' => $table,
                            'sensitive_column' => $column,
                            'fillable' => $modelFillable,
                            'guarded' => $modelGuarded,
                            'recommendation' => 'Ensure sensitive columns are properly guarded or use encrypted casting',
                        ];
                        break; // Only report once per model
                    }
                }

            } catch (\Exception $e) {
                Log::error("Error reviewing model {$className}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $modelsNeedingReview;
    }
}
