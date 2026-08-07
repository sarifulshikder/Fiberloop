# Fiberloop Deployment Strategy & Orchestration Decision

## Decision: Docker Compose + Manual Orchestration (with Kubernetes Future Path)

### Decision Date: 2026-08-07
### Decision Maker: Engineering Team
### Status: Approved for Phase 18

---

## Executive Summary

For Phase 18 (DevOps, CI/CD & Deployment), we have decided to use **Docker Compose** for container orchestration in staging and production environments, with a clear migration path to Kubernetes when the team and infrastructure mature.

### Why Not Kubernetes Now?

| Factor | Current Reality | Kubernetes Requirement | Gap |
|-------|----------------|---------------------|-----|
| Team Size | 1-2 DevOps engineers (part-time) | 1+ dedicated DevOps engineer | Insufficient dedicated resources |
| Scale | < 10,000 concurrent users | Optimized for 10,000+ | Current scale doesn't justify complexity |
| Deployment Frequency | Weekly/monthly | Daily+ to justify | Too infrequent for K8s overhead |
| Infrastructure | Single region, simple topology | Multi-region, complex topology | Current setup is simple |
| Budget | Limited cloud resources | Higher infrastructure costs | Budget constraints |

### Why Docker Compose?

1. **Simplicity**: Docker Compose provides 80% of orchestration benefits with 20% of the complexity
2. **Familiarity**: All team members are familiar with Docker Compose
3. **Speed**: Faster to implement and debug
4. **Cost**: Lower infrastructure and operational overhead
5. **Sufficiency**: Handles our current scale and deployment frequency adequately

### Future Kubernetes Migration Path

We will migrate to Kubernetes when **at least 2 of the following 3 conditions are met**:

1. **Team Growth**: 2+ dedicated DevOps/SRE engineers
2. **Scale Growth**: 50,000+ concurrent users or 200,000+ total users
3. **Deployment Frequency**: 10+ deployments per day across multiple services

Current status: 0/3 conditions met.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        CDN (Cloudflare)                             │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                     Nginx (Load Balancer)                          │
│  - SSL Termination (HTTPS)                                       │
│  - Static file serving                                           │
│  - Rate limiting                                                 │
│  - Request routing to Octane                                     │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                   Laravel Octane (FrankenPHP)                       │
│  - PHP 8.4 application server                                    │
│  - High-performance HTTP handling                                 │
│  - Worker processes for concurrent requests                       │
└─────────────────────────────────────────────────────────────────┘
                                │
        ┌───────────────────────────┼───────────────────────────┐
        ▼                           ▼                           ▼
┌───────────────┐         ┌─────────────┐             ┌─────────────┐
│  PostgreSQL   │         │   Redis     │             │  FreeRADIUS │
│  - Primary DB │         │  - Cache    │             │  - AAA      │
│  - Read replicas│        │  - Queue    │             │  - Auth     │
│  - Failover   │         │  - Sessions │             │  - Acct     │
└───────────────┘         └─────────────┘             └─────────────┘
        ▲                           ▲
        │                           │
┌───────────────────────────┼───────────────────────────┐
        │                           │
┌───────────────┐         ┌─────────────┐
│  Queue        │         │   AI        │
│  - Horizon    │         │  Service    │
│  - Workers    │         │  - ML Models │
│  - Priorities │         │  - Python   │
└───────────────┘         └─────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                      Monitoring Stack                             │
│  - Prometheus: Metrics collection                                 │
│  - Grafana: Dashboards & visualization                             │
│  - Sentry: Error tracking                                         │
│  - Elasticsearch/Kibana: Log aggregation                          │
└─────────────────────────────────────────────────────────────────┘
```

---

## Container Layout

### Production Environment (docker-compose.production.yml)

| Service | Replicas | Memory | CPU | Purpose |
|---------|----------|---------|-----|---------|
| nginx | 1 | 256MB | 0.5 | Web server / Load balancer |
| app | 4 | 1GB each | 1.0 | Laravel Octane workers |
| reverb | 2 | 512MB each | 0.5 | WebSocket server |
| postgres | 1 | 4GB | 2.0 | Primary database |
| postgres-replica | 1 | 2GB | 1.0 | Read replica |
| redis | 1 | 2GB | 1.0 | Cache & queue |
| freeradius | 2 | 512MB each | 0.5 | RADIUS authentication |
| horizon | 1 | 1GB | 0.5 | Queue monitoring |
| queue-high | 3 | 512MB each | 0.5 | High priority jobs |
| queue-low | 1 | 256MB | 0.25 | Low priority jobs |
| ai-service | 1 | 2GB | 1.0 | ML predictions |
| prometheus | 1 | 1GB | 0.5 | Metrics collection |
| grafana | 1 | 512MB | 0.25 | Monitoring dashboards |
| elasticsearch | 1 | 4GB | 1.0 | Log storage |
| kibana | 1 | 1GB | 0.5 | Log visualization |

### Staging Environment (docker-compose.staging.yml)

| Service | Replicas | Memory | CPU | Purpose |
|---------|----------|---------|-----|---------|
| nginx | 1 | 128MB | 0.25 | Web server |
| app | 2 | 512MB each | 0.5 | Laravel Octane workers |
| reverb | 1 | 256MB | 0.25 | WebSocket server |
| postgres | 1 | 1GB | 0.5 | Database |
| redis | 1 | 512MB | 0.25 | Cache & queue |
| freeradius | 1 | 256MB | 0.25 | RADIUS authentication |
| horizon | 1 | 512MB | 0.25 | Queue monitoring |
| queue-high | 2 | 256MB each | 0.25 | High priority jobs |
| queue-low | 1 | 128MB | 0.1 | Low priority jobs |
| ai-service | 1 | 1GB | 0.5 | ML predictions |
| prometheus | 1 | 512MB | 0.25 | Metrics collection |
| grafana | 1 | 256MB | 0.1 | Monitoring dashboards |
| elasticsearch | 1 | 2GB | 0.5 | Log storage |
| kibana | 1 | 512MB | 0.25 | Log visualization |

### Development Environment (docker-compose.yml)

| Service | Replicas | Memory | Purpose |
|---------|----------|---------|---------|
| nginx | 1 | Auto | Web server |
| app | 1 | Auto | Laravel Octane |
| reverb | 1 | Auto | WebSocket server |
| postgres | 1 | Auto | Database |
| redis | 1 | Auto | Cache & queue |
| freeradius | 1 | Auto | RADIUS authentication |
| queue | 1 | Auto | Queue worker |
| ai-service | 1 | Auto | ML predictions |

---

## CI/CD Pipeline

### Flow

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Developer  │────▶│    GitHub    │────▶│  CI Pipeline │
│   Push/MR    │     │    Actions   │     │   (GitHub)   │
└─────────────┘     └─────────────┘     └─────────────┘
                                                     │
┌─────────────────────────────────────────────────────────────────┐
│                           CI Pipeline                              │
├─────────────────────────────────────────────────────────────────┤
│  1. Lint (Pint)                  → Fast feedback                    │
│  2. Static Analysis (PHPStan)   → Code quality                     │
│  3. Unit Tests (PHPUnit/Pest)   → Business logic                   │
│  4. Feature Tests               → Integration                      │
│  5. Security Scans              → Vulnerability detection          │
│  6. Build Artifacts             → Deployment package               │
│  7. Database Backup Test        → Verify backup/restore           │
└─────────────────────────────────────────────────────────────────┘
                                                     │
                      ┌──────────────────────────────┼──────────────────────────────┐
                      ▼                              ▼                              ▼
              ┌────────────────┐              ┌────────────────┐              ┌────────────────┐
              │   Auto-Deploy  │              │   Auto-Deploy  │              │   Manual       │
              │   to Staging   │              │   to Production │              │   Production   │
              │  (develop)     │              │   (main)        │              │   Deploy       │
              └────────────────┘              └────────────────┘              └────────────────┘
                     │                              │                              │
                     ▼                              ▼                              ▼
              ┌────────────────┐              ┌────────────────┐              ┌────────────────┐
              │  Verify Staging │              │  Verify Produc- │              │  Verify Produc- │
              │  Deployment     │              │  tion Deploy    │              │  tion Deploy    │
              └────────────────┘              └────────────────┘              └────────────────┘
```

### Trigger Matrix

| Branch | Event | Staging Deploy | Production Deploy |
|--------|-------|----------------|------------------|
| main | push | Auto | Auto (after tests) |
| main | PR merge | Auto | Auto |
| develop | push | Auto | No |
| develop | PR | No | No |
| release/* | push | Auto | Manual approval |
| feature/* | push | No | No |
| feature/* | PR | No | No |
| hotfix/* | push | Auto | Manual approval |

### Required Approvals

| Environment | Required Approvals | Who Can Approve |
|-------------|---------------------|----------------|
| Staging | None (auto) | N/A |
| Production (main branch) | None (auto after CI) | N/A |
| Production (release/hotfix) | 1 | super_admin, admin |
| Production (manual deploy) | 2 | super_admin, admin |

---

## Zero-Downtime Deployment Strategy

### Overview

We use a **blue-green deployment** strategy with the following steps:

1. **Build**: Create deployment artifact in CI
2. **Extract**: Extract artifact to new release directory on server
3. **Pre-deploy checks**: Verify database connectivity, disk space, migrations
4. **Maintenance mode**: Enable maintenance page (production only)
5. **Queue drain**: Pause Horizon, wait for jobs to finish
6. **Migrations**: Run database migrations (safe, non-destructive)
7. **Cache clear**: Clear all caches
8. **Cache warm**: Rebuild caches
9. **Symlink switch**: Point 'current' to new release
10. **Verify**: Check health endpoints
11. **Queue resume**: Resume Horizon processing
12. **Maintenance off**: Disable maintenance mode

### Migration Safety

**Safe migrations** (can run without downtime):
- Creating new tables
- Adding new columns (nullable, with default)
- Adding indexes (concurrently)
- Inserting data

**Unsafe migrations** (require downtime):
- Dropping tables
- Dropping columns
- Renaming tables/columns
- Changing column types
- Large data migrations

**Policy**: All production migrations must be safe. If an unsafe migration is needed:
1. Schedule during maintenance window
2. Notify all stakeholders
3. Have rollback plan ready
4. Test in staging first

### Rollback Strategy

1. **Fast rollback** (< 2 minutes): Switch symlink back to previous release
2. **With database changes**: Restore from backup (tested weekly)
3. **Data corruption**: Use point-in-time recovery from PostgreSQL WAL logs

---

## Monitoring & Alerting

### Health Endpoints

| Endpoint | Purpose | Expected Response |
|----------|---------|------------------|
| GET /health | Comprehensive health check | 200 with JSON status |
| GET /health/ping | Simple liveness check | 200 with "pong" |
| GET /metrics | Prometheus metrics | 200 with metrics text |

### Alert Severities

| Severity | Response Time | Notification Methods |
|----------|---------------|---------------------|
| Critical | Immediate (24/7) | Slack + SMS + PagerDuty + Email |
| High | Within 1 hour (business hours) | Slack + Email + PagerDuty |
| Medium | Within 4 hours | Slack + Email |
| Low | Within 24 hours | Slack |
| Info | No SLA | Log only |

### Critical Alerts (24/7 On-Call)

| Alert | Condition | Impact | Escalation |
|-------|-----------|--------|------------|
| RadiusServiceDown | RADIUS unreachable for >1 min | No customer internet | Page NOC team |
| PostgresServiceDown | Database unreachable for >1 min | All data operations fail | Page DBA team |
| RedisServiceDown | Redis unreachable for >1 min | Cache/queue/sessions fail | Page DevOps team |
| ApplicationDown | App returns 5xx for >1 min | Admin/API unavailable | Page Dev team |
| BillingRunFailed | Billing job fails 3+ times | Invoices not generated | Page Billing team |
| PaymentProcessingFailed | Payment job fails 3+ times | Payments not recorded | Page Billing team |

### Monitoring Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                         Prometheus                                │
│  - Scrapes metrics from all services every 15s                    │
│  - Alert rules evaluated every 15s                              │
│  - Alertmanager handles notifications                            │
└─────────────────────────────────────────────────────────────────┘
                                │
                ┌───────────────────┼───────────────────┐
                ▼                   ▼                   ▼
        ┌───────────────┐   ┌─────────────┐   ┌─────────────┐
        │  Application   │   │   Database   │   │   Radius     │
        │  Metrics      │   │   Metrics    │   │   Metrics    │
        └───────────────┘   └─────────────┘   └─────────────┘
                │                   │                   │
                └───────────────────┼───────────────────┘
                                    ▼
                            ┌───────────────────┐
                            │    Grafana          │
                            │  - Dashboards      │
                            │  - Alert visuals   │
                            │  - Team views      │
                            └───────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                      Elasticsearch Stack                           │
│  - Elasticsearch: Log storage and search                           │
│  - Logstash: Log processing and enrichment                         │
│  - Kibana: Log exploration and visualization                       │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                         Sentry                                     │
│  - Error tracking                                                      │
│  - Performance monitoring                                               │
│  - User feedback                                                       │
└─────────────────────────────────────────────────────────────────┘
```

### Key Metrics to Monitor

| Metric | Description | Threshold | Alert Severity |
|--------|-------------|-----------|----------------|
| fiberloop_up | Application uptime | < 1 | Critical |
| fiberloop_database_connections | DB connection count | > 150 | Warning |
| fiberloop_queue_size | Jobs waiting | > 100 | Warning |
| fiberloop_cache_hit_rate | Cache efficiency | < 80% | Warning |
| fiberloop_memory_usage | PHP memory | > 80% | Warning |
| fiberloop_customers_total | Customer count | N/A | Info |
| fiberloop_invoices_overdue | Overdue invoices | > 1000 | High |
| fiberloop_payments_today | Daily payments | N/A | Info |

---

## Backup & Disaster Recovery

### Backup Strategy

| Data | Frequency | Retention | Storage |
|------|-----------|-----------|---------|
| Application code | On deploy | Forever | Git + S3 |
| Database | Daily at 3 AM | 30 days | S3 + Offsite |
| Database | Hourly WAL | 7 days | S3 |
| Redis | Daily | 7 days | S3 |
| Logs | Daily | 90 days | S3/Elasticsearch |
| Backups | Weekly | 1 year | S3 Glacier |

### Restore Testing

- **Daily**: Verify backup files are created and accessible
- **Weekly**: Test restore from most recent backup to staging
- **Monthly**: Test point-in-time recovery from WAL logs
- **Quarterly**: Full disaster recovery drill (simulated outage)

### Disaster Recovery Plan

**Scenario: Primary Database Failure**
1. Promote read replica to primary (1-2 minutes)
2. Update application configuration
3. Verify data consistency
4. Investigate and fix primary database

**Scenario: Application Server Failure**
1. Start new container on different host
2. Switch load balancer traffic
3. Verify health checks
4. Replace failed hardware

**Scenario: Data Center Outage**
1. Failover to secondary region (if configured)
2. DNS update to point to secondary
3. Database failover
4. Verify all services in secondary region

**Recovery Time Objectives (RTO)**:
- Application server: 5 minutes
- Database failover: 15 minutes
- Data center failover: 1 hour (if secondary region available)

**Recovery Point Objectives (RPO)**:
- Database: 5 minutes (with WAL archiving)
- Redis: 1 hour
- Logs: 1 hour

---

## Log Retention Policy

### Application Logs

| Log Type | Retention | Storage | Purpose |
|----------|-----------|---------|---------|
| Application (laravel.log) | 30 days | Local disk + Elasticsearch | Debugging |
| Access (nginx) | 90 days | Elasticsearch | Security auditing |
| Error (nginx) | 1 year | Elasticsearch | Compliance |
| Slow query (PostgreSQL) | 30 days | Elasticsearch | Performance |
| Queue (Horizon) | 30 days | Local disk + Elasticsearch | Job tracking |
| Security | 1 year | Elasticsearch + S3 | Compliance |
| Deployments | 1 year | S3 | Audit trail |

### Log Rotation

- **Local files**: Rotated daily, kept for 7 days
- **Elasticsearch**: Index pattern `fiberloop-logs-*` with 90-day retention
- **S3**: Raw logs archived for 1 year, compressed

### Sensitive Data in Logs

**Must NOT be logged**:
- Passwords
- API keys
- Credit card numbers
- NID numbers (Bangladeshi national ID)
- Bank account numbers
- Personal addresses
- Session tokens

**Masking**: Use Laravel's logging configuration to mask sensitive fields:
```php
'logging' => [
    'mask_fields' => ['password', 'secret', 'token', 'nid', 'credit_card'],
],
```

---

## Security Considerations

### Secrets Management

**Production**: Use environment-specific `.env` files managed via:
1. GitHub Actions secrets (for CI/CD)
2. Ansible vault (for server configuration)
3. Manual placement on servers (for initial setup)

**Development/Staging**: Docker secrets or encrypted environment files

**Never commit to Git**:
- Database passwords
- API keys
- Encryption keys
- Payment gateway credentials
- RADIUS secrets

### Network Security

- **Firewall**: Restrict server access to known IPs
- **SSH**: Key-based authentication only, no password
- **Database**: SSL connections required, no external access
- **RADIUS**: Firewall rules to allow only from trusted NAS devices
- **API**: Rate limiting, authentication required

### Compliance

- **Data at rest**: All databases encrypted
- **Data in transit**: SSL/TLS 1.2+ for all external communications
- **Audit trail**: All financial operations logged with actor and timestamp
- **Access control**: RBAC implemented via spatie/laravel-permission

---

## Future Enhancements

### Phase 19+ Roadmap

1. **Kubernetes Migration** (When 2/3 conditions met)
   - Helm charts for application
   - Kubernetes manifests for infrastructure
   - CI/CD pipeline integration
   - Monitoring and logging adaptation

2. **Infrastructure as Code**
   - Terraform for cloud resources
   - Ansible for server configuration
   - Packer for image building

3. **Advanced Monitoring**
   - Distributed tracing (OpenTelemetry)
   - Synthetic monitoring
   - SLO/SLI tracking
   - Capacity planning

4. **Advanced Alerting**
   - Machine learning based anomaly detection
   - Predictive alerting
   - Automated remediation (select scenarios)

5. **Multi-Region Deployment**
   - Database replication
   - Application load balancing
   - Disaster recovery automation

### Kubernetes Architecture (Future)

```yaml
# Simplified Kubernetes deployment structure
apiVersion: apps/v1
kind: Deployment
metadata:
  name: fiberloop-app
spec:
  replicas: 4
  selector:
    matchLabels:
      app: fiberloop
  template:
    spec:
      containers:
      - name: app
        image: fiberloop/app:{{ version }}
        ports:
        - containerPort: 9000
        resources:
          requests:
            memory: "512Mi"
            cpu: "500m"
          limits:
            memory: "1Gi"
            cpu: "1"
        envFrom:
        - secretRef:
            name: fiberloop-secrets
      - name: queue
        image: fiberloop/app:{{ version }}
        command: ["php", "artisan", "horizon"]
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"
```

---

## Appendices

### Appendix A: Environment Variables

See `.env.example` for full list of required environment variables.

### Appendix B: Server Requirements

**Production Application Server**:
- CPU: 4+ cores
- Memory: 8GB+
- Disk: 100GB SSD
- OS: Ubuntu 22.04 LTS

**Production Database Server**:
- CPU: 4+ cores
- Memory: 8GB+
- Disk: 200GB SSD (or more based on data size)
- OS: Ubuntu 22.04 LTS

**Production Redis Server**:
- CPU: 2+ cores
- Memory: 4GB+
- Disk: 50GB SSD
- OS: Ubuntu 22.04 LTS

### Appendix C: Contact Information

| Team | Primary Contact | Secondary Contact | Escalation |
|------|-----------------|-------------------|------------|
| Development | dev@fiberloop.com | lead-dev@fiberloop.com | CTO |
| DevOps | devops@fiberloop.com | lead-devops@fiberloop.com | CTO |
| NOC | noc@fiberloop.com | lead-noc@fiberloop.com | Engineering Manager |
| DBA | dba@fiberloop.com | lead-dba@fiberloop.com | Engineering Manager |
| Billing | billing@fiberloop.com | lead-billing@fiberloop.com | Finance Manager |
| Security | security@fiberloop.com | lead-security@fiberloop.com | CISO |

### Appendix D: Runbooks

See separate documentation in `docs/runbooks/` for:
- [Database Failover](runbooks/database-failover.md)
- [Application Outage](runbooks/application-outage.md)
- [Billing Failure](runbooks/billing-failure.md)
- [RADIUS Issues](runbooks/radius-issues.md)
- [Payment Processing](runbooks/payment-processing.md)

---

## Revision History

| Date | Author | Changes |
|------|--------|---------|
| 2026-08-07 | Engineering Team | Initial version for Phase 18 |
