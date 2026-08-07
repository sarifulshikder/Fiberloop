# Fiberloop Rollback Plan

**Document Version:** 1.0  
**Last Updated:** 2026-08-07  
**Owner:** DevOps Team  
**Status:** Ready for Production

---

## 🚨 Rollback Trigger Conditions

### Automatic Rollback (System-Initiated)
| Condition | Threshold | Action |
|-----------|-----------|--------|
| Deployment health check failure | 3 consecutive failures | Auto-rollback to previous release |
| Application 5xx error rate | > 10% for 2 minutes | Auto-rollback |
| Database connection failures | > 50% for 1 minute | Auto-rollback + alert |
| Queue failure rate | > 20% for 5 minutes | Auto-rollback |
| Memory usage (per container) | > 95% for 3 minutes | Auto-rollback + scale up |

### Manual Rollback (Human-Initiated)
| Scenario | Trigger | Approval Required |
|----------|---------|-------------------|
| Critical bug in production | Bug affects > 10% users | super_admin |
| Security vulnerability | CVSS > 7.0 | security team |
| Data corruption | Verified data loss | super_admin + DBA |
| Performance degradation | Response time > 5s | super_admin |
| Payment processing failure | > 5% failures | billing manager + super_admin |
| RADIUS authentication failure | > 1% failures | NOC lead + super_admin |

---

## 📋 Rollback Types

### Type 1: Fast Rollback (Code Only) - < 2 minutes
**Use Case:** Bug in application code, configuration issue

**Steps:**
1. Switch symlink to previous release
2. Clear caches
3. Restart queue workers
4. Verify

**Commands:**
```bash
# On production server
cd /opt/fiberloop

# 1. Switch symlink to previous release
ln -nfs releases/PREVIOUS_RELEASE_HASH current

# 2. Clear all caches
php current/artisan config:clear
php current/artisan route:clear
php current/artisan view:clear
php current/artisan cache:clear
php current/artisan optimize

# 3. Restart queue workers
php current/artisan queue:restart

# 4. Verify deployment
curl -sSf https://fiberloop.com/health
```

**Rollback Time:** ~30-60 seconds  
**Data Loss:** None  
**Downtime:** 0-5 seconds (cache warm-up)

---

### Type 2: Database Rollback (Code + Schema) - 5-15 minutes
**Use Case:** Failed database migration, data corruption

**Prerequisites:**
- Database backup from before migration available
- Migration is reversible (has `down()` method)
- No critical data created since migration

**Steps:**
1. Enable maintenance mode
2. Switch symlink to previous release
3. Run migration rollback
4. Restore from backup (if needed)
5. Clear caches
6. Restart queue workers
7. Disable maintenance mode
8. Verify

**Commands:**
```bash
# On production server
cd /opt/fiberloop

# 1. Enable maintenance mode
php current/artisan down --message="Emergency maintenance - Rollback in progress" --retry=300

# 2. Switch symlink to previous release
ln -nfs releases/PREVIOUS_RELEASE_HASH current

# 3. Rollback migrations (one batch at a time)
php current/artisan migrate:rollback --batch=LATEST_BATCH_NUMBER --force

# 4. If data corruption, restore from backup
php current/artisan db:restore /backups/pre-migration-backup.sql.gz --force

# 5. Clear all caches
php current/artisan config:cache
php current/artisan route:cache
php current/artisan view:cache
php current/artisan cache:clear

# 6. Restart queue workers
php current/artisan queue:restart

# 7. Disable maintenance mode
php current/artisan up

# 8. Verify deployment
curl -sSf https://fiberloop.com/health
```

**Rollback Time:** 5-15 minutes  
**Data Loss:** Data created since migration (if not backed up)  
**Downtime:** Full system downtime during rollback

---

### Type 3: Full Rollback (Code + Database + Config) - 15-60 minutes
**Use Case:** Major incident, security breach, complete deployment failure

**Steps:**
1. Announce incident to all stakeholders
2. Freeze all deployments
3. Snapshot current state (for post-mortem)
4. Enable maintenance mode
5. Stop all application containers
6. Restore database from last known good backup
7. Switch symlink to last known good release
8. Start application containers
9. Clear caches
10. Restart queue workers
11. Verify all services
12. Disable maintenance mode
13. Announce resolution

**Commands:**
```bash
# 1. Announce incident (via Slack, email, status page)
# Use AlertManager: php artisan alert:incident "Production rollback initiated"

# 2. Freeze deployments
# Lock GitHub Actions, CI/CD pipeline

# 3. Snapshot current state
TIMSTAMP=$(date +%Y%m%d_%H%M%S)
mkdir -p /opt/fiberloop/snapshots/incident_${TIMSTAMP}
cp -r current/ /opt/fiberloop/snapshots/incident_${TIMSTAMP}/code/
mysqldump -u fiberloop -p -h postgres fiberloop > /opt/fiberloop/snapshots/incident_${TIMSTAMP}/db.sql

# 4. Enable maintenance mode
php current/artisan down --message="Emergency maintenance - Full system rollback" --retry=3600

# 5. Stop all application containers
docker-compose -f docker-compose.production.yml down

# 6. Restore database from last known good backup
# Find latest backup
LATEST_BACKUP=$(ls -t /opt/fiberloop/backups/*.sql.gz | head -1)
php current/artisan db:restore ${LATEST_BACKUP} --force --drop

# 7. Switch to last known good release
LAST_GOOD_RELEASE=$(ls -t /opt/fiberloop/releases/ | grep -v current | head -1)
ln -nfs /opt/fiberloop/releases/${LAST_GOOD_RELEASE} /opt/fiberloop/current

# 8. Start application containers
docker-compose -f docker-compose.production.yml up -d

# 9. Clear all caches
php current/artisan config:cache
php current/artisan route:cache
php current/artisan view:cache
php current/artisan cache:clear
php current/artisan optimize

# 10. Restart queue workers
php current/artisan queue:restart

# 11. Verify all services
curl -sSf https://fiberloop.com/health
curl -sSf https://fiberloop.com/admin
curl -sSf https://fiberloop.com/customer

# 12. Disable maintenance mode
php current/artisan up

# 13. Announce resolution
# Use AlertManager: php artisan alert:resolve "Production rollback completed"
```

**Rollback Time:** 15-60 minutes  
**Data Loss:** Data created since last good backup  
**Downtime:** Full system downtime during entire rollback

---

## 🎯 Rollback Targets

### Symlink-Based Rollback
```
/opt/fiberloop/
├── current -> releases/20260807_120000  # Current (bad)
├── releases/
│   ├── 20260807_120000/      # Bad release
│   ├── 20260807_110000/      # Previous (good)
│   └── 20260807_100000/      # Before that
└── storage/
```

### Release Identification
```bash
# List releases (newest first)
ls -lt /opt/fiberloop/releases/ | grep -E '^[d]' | awk '{print $NF}'

# Get current release
readlink /opt/fiberloop/current

# Get previous release (for rollback)
ls -t /opt/fiberloop/releases/ | grep -v current | head -2 | tail -1
```

---

## 📊 Rollback Decision Matrix

| Scenario | Rollback Type | Approval | Time | Downtime | Data Loss |
|----------|---------------|----------|------|-----------|-----------|
| Bug in API | Type 1 | Dev Lead | < 2 min | Minimal | None |
| Configuration error | Type 1 | Dev Lead | < 2 min | Minimal | None |
| Failed migration (reversible) | Type 2 | super_admin | 5-15 min | Full | Since migration |
| Data corruption | Type 2 | super_admin + DBA | 5-15 min | Full | Since last backup |
| Security breach | Type 3 | super_admin + Security | 15-60 min | Full | Since last backup |
| Major incident | Type 3 | super_admin + All | 15-60 min | Full | Since last backup |

---

## 👥 Rollback Team Roles

### Rollback Commander (Required)
- **Role:** super_admin
- **Responsibilities:**
  - Decide when to rollback
  - Approve rollback type
  - Coordinate with all teams
  - Announce rollback to stakeholders

### Technical Lead (Required)
- **Role:** DevOps/SRE
- **Responsibilities:**
  - Execute rollback commands
  - Monitor rollback progress
  - Verify system health after rollback
  - Troubleshoot issues during rollback

### Database Lead (Optional - for Type 2/3)
- **Role:** DBA
- **Responsibilities:**
  - Approve database rollback
  - Execute database restore
  - Verify data integrity
  - Assess data loss

### Application Lead (Optional)
- **Role:** Dev Lead
- **Responsibilities:**
  - Identify root cause
  - Assess impact of rollback
  - Verify application functionality
  - Plan forward fix

### Communication Lead (Required)
- **Role:** Product Manager/ Support Lead
- **Responsibilities:**
  - Draft customer communications
  - Update status page
  - Notify internal stakeholders
  - Manage external communications

---

## 📞 Communication Plan

### Internal Communication
| Channel | Purpose | Timeline |
|---------|---------|----------|
| Slack #incidents | Initial alert | Immediate |
| Slack #devops | Technical coordination | During rollback |
| Slack #executives | Executive updates | Every 5 minutes |
| Email (incident@) | Detailed status | Every 15 minutes |
| PagerDuty | Escalation | Immediate |

### External Communication (If Applicable)
| Channel | Message | Timeline |
|---------|---------|----------|
| Status Page | "Investigating" | Immediate |
| Status Page | "Identified" | Within 5 min |
| Status Page | "Rollback in progress" | During rollback |
| Status Page | "Resolved" | After verification |
| Customer Email | Incident summary | After resolution |

---

## 🔄 Post-Rollback Procedures

### Immediate (Within 1 Hour)
1. [ ] Verify all services are operational
   - Application: `curl -sSf https://fiberloop.com/health`
   - Database: `pg_isready -h postgres -U fiberloop`
   - Redis: `redis-cli -a ${PASSWORD} ping`
   - Queue: Check Horizon dashboard
   - RADIUS: Test authentication

2. [ ] Verify critical business functions
   - Customer login
   - Billing run
   - Payment processing
   - RADIUS authentication
   - Ticket creation

3. [ ] Check monitoring dashboards
   - Prometheus: All metrics reporting
   - Grafana: All dashboards loading
   - Sentry: No new errors
   - Elasticsearch: Logs flowing

### Short Term (Within 24 Hours)
1. [ ] Conduct post-mortem analysis
   - Identify root cause
   - Document timeline
   - Identify contributing factors
   - Document lessons learned

2. [ ] Implement fixes
   - Fix the original issue
   - Add additional safeguards
   - Update rollback procedures
   - Improve testing

3. [ ] Schedule re-deployment
   - Plan new deployment window
   - Review rollback plan
   - Update stakeholders

### Long Term (Within 1 Week)
1. [ ] Implement preventative measures
   - Add automated tests for the issue
   - Improve monitoring/alerting
   - Update documentation
   - Conduct team training

2. [ ] Review and update rollback plan
   - Incorporate lessons learned
   - Update decision matrix
   - Improve automation
   - Update communication plan

---

## 📝 Rollback Checklist

### Pre-Rollback
- [ ] Identify rollback type (1, 2, or 3)
- [ ] Confirm approval from required personnel
- [ ] Verify previous release is available
- [ ] Verify backup is available (for Type 2/3)
- [ ] Notify all stakeholders
- [ ] Freeze all deployments
- [ ] Snapshot current state (for Type 3)

### During Rollback
- [ ] Execute rollback commands
- [ ] Monitor rollback progress
- [ ] Verify each step completes successfully
- [ ] Document any issues
- [ ] Escalate if problems occur

### Post-Rollback
- [ ] Verify all services operational
- [ ] Verify critical business functions
- [ ] Check monitoring dashboards
- [ ] Announce resolution
- [ ] Conduct post-mortem
- [ ] Implement fixes
- [ ] Schedule re-deployment

---

## 🛡️ Rollback Safeguards

### Automated Safeguards
1. **Health Check Failures:** Auto-rollback if health check fails 3 times
2. **Error Rate:** Auto-rollback if 5xx rate > 10% for 2 minutes
3. **Database Failures:** Alert (not auto-rollback) if DB connection fails
4. **Queue Failures:** Auto-rollback if queue failure rate > 20% for 5 minutes

### Manual Safeguards
1. **Pre-deployment Checks:** Verify database, Redis, disk space before deploy
2. **Migration Safety:** Block destructive migrations without manual approval
3. **Maintenance Mode:** Always enable before database rollback
4. **Backup Verification:** Verify backup exists before Type 2/3 rollback
5. **Queue Draining:** Drain queue before deployment, resume after

---

## 📚 Related Documentation

- [Deployment Strategy](DEPLOYMENT_STRATEGY.md)
- [Monitoring Runbook](MONITORING.md)
- [Database Failover Runbook](DATABASE_FAILOVER.md)
- [Incident Response Plan](INCIDENT_RESPONSE.md)

---

## 📞 Emergency Contacts

| Role | Name | Phone | Email | Slack |
|------|------|-------|-------|-------|
| Rollback Commander | | +1 XXX-XXX-XXXX | | @ |
| Technical Lead | | +1 XXX-XXX-XXXX | | @ |
| Database Lead | | +1 XXX-XXX-XXXX | | @ |
| Application Lead | | +1 XXX-XXX-XXXX | | @ |
| Communication Lead | | +1 XXX-XXX-XXXX | | @ |

**Escalation Path:** Technical Lead → Rollback Commander → CTO

---

**Document Control**
- **Version:** 1.0
- **Last Updated:** 2026-08-07
- **Next Review:** 2026-11-07
- **Owner:** DevOps Team