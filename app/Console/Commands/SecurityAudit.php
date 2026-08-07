<?php

namespace App\Console\Commands;

use App\Services\Security\SecurityAuditService;
use Illuminate\Console\Command;

/**
 * Artisan command to run a comprehensive security audit.
 * This command checks for SQL injection vulnerabilities, mass assignment issues,
 * and other security concerns.
 */
class SecurityAudit extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'security:audit {--mass-assignment : Audit mass assignment vulnerabilities} {--queries : Audit database queries} {--full : Run full security audit}';

    /**
     * The console command description.
     */
    protected $description = 'Run a comprehensive security audit of the application';

    /**
     * Execute the console command.
     */
    public function handle(SecurityAuditService $auditService): int
    {
        $this->info('Starting security audit...');

        if ($this->option('mass-assignment') || $this->option('full')) {
            $this->auditMassAssignment($auditService);
        }

        if ($this->option('queries') || $this->option('full')) {
            $this->auditQueries($auditService);
        }

        if ($this->option('full')) {
            $this->auditModelsForReview($auditService);
        }

        if (!$this->option('mass-assignment') && !$this->option('queries') && !$this->option('full')) {
            $this->error('Please specify an audit type: --mass-assignment, --queries, or --full');
            return 1;
        }

        $this->info('Security audit completed.');
        return 0;
    }

    /**
     * Audit mass assignment vulnerabilities.
     */
    protected function auditMassAssignment(SecurityAuditService $auditService): void
    {
        $this->info('\n=== Mass Assignment Audit ===');

        $report = $auditService->auditMassAssignment();

        if (empty($report['vulnerable_models'])) {
            $this->info('No mass assignment vulnerabilities found.');
        } else {
            $this->error('Found ' . count($report['vulnerable_models']) . ' models with mass assignment vulnerabilities:');

            foreach ($report['vulnerable_models'] as $vulnerability) {
                $this->line('');
                $this->error('Model: ' . $vulnerability['model']);
                $this->error('Issue: ' . $vulnerability['issue']);
                $this->error('Severity: ' . $vulnerability['severity']);
                $this->info('Recommendation: ' . $vulnerability['recommendation']);
            }
        }

        $this->info('Total models checked: ' . $report['total_models_checked']);
        $this->info('Safe models: ' . count($report['safe_models']));
    }

    /**
     * Audit database queries.
     */
    protected function auditQueries(SecurityAuditService $auditService): void
    {
        $this->info('\n=== Database Query Audit ===');

        // Enable query logging
        config(['database.log_queries' => true]);

        $report = $auditService->auditRawQueries();

        if (empty($report['raw_queries'])) {
            $this->info('No unsafe raw queries detected.');
        } else {
            $this->error('Found ' . count($report['raw_queries']) . ' potentially unsafe queries:');

            foreach ($report['raw_queries'] as $query) {
                $this->line('');
                $this->error('Risk: ' . $query['risk']);
                $this->error('SQL: ' . substr($query['sql'], 0, 100) . '...');
                $this->info('Recommendation: ' . $query['recommendation']);
            }
        }

        $this->info('Total queries checked: ' . $report['total_queries']);
        $this->info('Parameterized queries: ' . count($report['parameterized_queries']));
    }

    /**
     * Audit models for security review.
     */
    protected function auditModelsForReview(SecurityAuditService $auditService): void
    {
        $this->info('\n=== Models Needing Security Review ===');

        $models = $auditService->getModelsNeedingReview();

        if (empty($models)) {
            $this->info('No models need security review.');
            return;
        }

        $this->error('Found ' . count($models) . ' models that need security review:');

        foreach ($models as $model) {
            $this->line('');
            $this->error('Model: ' . $model['model']);
            $this->error('Table: ' . $model['table']);
            $this->error('Sensitive column: ' . $model['sensitive_column']);
            $this->info('Recommendation: ' . $model['recommendation']);
        }
    }
}
