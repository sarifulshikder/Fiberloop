# Fiberloop - Full Project Verification System

This document explains how to verify that **ALL pages** in your Fiberloop project (Admin Panel, Customer Panel, API, and Public routes) are working without errors.

## 🎯 Quick Start

Run this single command to verify everything:

```bash
./verify.sh
```

This will:
1. ✅ Clear all caches
2. ✅ Run static analysis (PHPStan)
3. ✅ Run Pest HTTP tests for all routes
4. ✅ Run custom verification command
5. ✅ Run Dusk browser tests (if installed)
6. ✅ Check application logs for errors

---

## 📋 Available Verification Methods

| Method | Command | What it Tests | Speed | Browser Required |
|--------|---------|---------------|-------|-----------------|
| **Full Verification** | `./verify.sh` | Everything | Medium | Optional |
| **Custom Command** | `php artisan app:verify-all-pages` | All routes via HTTP | Fast | ❌ |
| **Pest HTTP Tests** | `php artisan test` | All routes | Fast | ❌ |
| **Dusk Browser Tests** | `php artisan dusk` | UI rendering | Slow | ✅ |
| **PHPStan** | `vendor/bin/phpstan analyse` | Code analysis | Fast | ❌ |
| **Individual Test** | `php artisan test tests/Feature/FullProjectVerificationTest.php` | Specific test | Fast | ❌ |

---

## 🔧 Setup Instructions

### 1. Install Required Dependencies

```bash
# Install Pest (already included in Laravel)
composer require pestphp/pest --dev

# Install PHPStan for static analysis
composer require --dev phpstan/phpstan

# Install Dusk for browser testing (optional)
composer require --dev laravel/dusk
php artisan dusk:install
```

### 2. Make Shell Script Executable

```bash
chmod +x verify.sh
```

---

## 📁 Files Created

| File | Purpose |
|------|---------|
| `tests/Feature/FullProjectVerificationTest.php` | Pest tests for Admin, Customer, API, and Public routes |
| `tests/Feature/AllRoutesTest.php` | Dynamically tests ALL registered routes |
| `tests/Browser/FullApplicationTest.php` | Dusk browser tests for UI navigation |
| `app/Console/Commands/VerifyAllPages.php` | Artisan command to verify all pages via HTTP |
| `phpstan.neon` | PHPStan configuration |
| `verify.sh` | Master script that runs all verification tools |
| `.github/workflows/verify-pages.yml` | GitHub Actions workflow for CI/CD |
| `VERIFICATION.md` | This documentation |

---

## 🚀 How to Use Each Method

### Method 1: Full Verification (Recommended)

```bash
# Run everything
./verify.sh

# With custom user IDs
./verify.sh --user=1 --customer=6

# Output as JSON
./verify.sh --output=json
```

### Method 2: Custom Artisan Command

```bash
# Basic usage
php artisan app:verify-all-pages

# With specific user IDs
php artisan app:verify-all-pages --user=1 --customer=6

# Output as JSON
php artisan app:verify-all-pages --output=json

# Check exit code (0 = success, 1 = failures)
php artisan app:verify-all-pages && echo "All good!" || echo "Issues found!"
```

### Method 3: Pest HTTP Tests

```bash
# Run all verification tests
php artisan test

# Run specific test file
php artisan test tests/Feature/FullProjectVerificationTest.php

# Run with verbose output
php artisan test -v

# Run with parallel execution
php artisan test --parallel
```

### Method 4: Dusk Browser Tests

```bash
# First install ChromeDriver (on Ubuntu/Debian)
sudo apt-get install -y google-chrome-stable chromedriver

# Run Dusk tests
php artisan dusk

# Run specific test
php artisan dusk tests/Browser/FullApplicationTest.php

# Run with headless mode
php artisan dusk --headless
```

### Method 5: PHPStan Static Analysis

```bash
# Basic analysis
vendor/bin/phpstan analyse

# With memory limit
vendor/bin/phpstan analyse --memory-limit=2G

# Analyse specific directory
vendor/bin/phpstan analyse app/Filament

# Show progress
vendor/bin/phpstan analyse --no-progress
```

---

## 🔄 Automated Testing

### GitHub Actions (Already Configured)

The workflow `.github/workflows/verify-pages.yml` runs automatically on:
- Every `push` to `main`, `develop`, or `feature/*` branches
- Every `pull_request` to `main` or `develop`
- Manual trigger via GitHub UI
- Daily at 2 AM UTC

**What it runs:**
1. PHPStan static analysis
2. Pest HTTP tests
3. All routes test
4. Custom verification command
5. Log error checking
6. Dusk browser tests (on non-scheduled runs)

### Run Locally with Same CI Commands

```bash
# Simulate CI locally
./verify.sh
```

---

## 🛠️ Customizing Verification

### Add More Routes to Test

Edit `tests/Feature/FullProjectVerificationTest.php` and add your custom routes:

```php
$adminRoutes = [
    // ... existing routes
    'filament.admin.resources.your-new-resource.index',
];
```

### Skip Specific Routes

Edit `tests/Feature/AllRoutesTest.php` and add to `$skippedPatterns`:

```php
$skippedPatterns = [
    // ... existing patterns
    'your-route-pattern',
];
```

### Add Custom Error Patterns

Edit `app/Console/Commands/VerifyAllPages.php` and add to `$errorPatterns`:

```php
$errorPatterns = [
    // ... existing patterns
    'Your custom error message',
];
```

---

## 📊 Understanding Results

### Pest Test Output

```
PASS  Tests\Feature\FullProjectVerificationTest
✓ All admin panel pages load without errors
✓ All customer panel pages load without errors
✓ All API routes return valid responses
✓ Public routes load without errors

Tests:  4 passed
Time:   4.23s
```

✅ **All passed** = No errors found
❌ **Failed** = Check the error message for details

### Artisan Command Output

```
🔍 Starting full application verification...

Testing admin panel...
✓ /admin
✓ /admin/customers
✓ /admin/invoices
...

Testing customer panel...
✓ /customer
✓ /customer/profiles
...

Testing API routes...
✓ /api/v1/customer/invoices
...

Testing public routes...
✓ /
✓ /login
...

═══════════════════════════════════════════════
📊 Results: 45/45 passed
✅ All pages verified successfully!
```

### Dusk Test Output

```
✓ admin panel navigation
✓ customer panel navigation
✓ public pages
```

---

## ⚠️ Troubleshooting

### "Dusk not installed"

```bash
composer require --dev laravel/dusk
php artisan dusk:install
```

### "ChromeDriver not found"

```bash
# Ubuntu/Debian
sudo apt-get install -y google-chrome-stable chromedriver

# Mac
homebrew install --cask chromedriver
```

### "PHPStan not found"

```bash
composer require --dev phpstan/phpstan
```

### "No customer with ID 6 found"

Create a test customer:

```bash
php artisan tinker
\App\Models\Customer::create([
    'first_name' => 'Test',
    'last_name' => 'Customer',
    'email' => 'test@customer.com',
    'phone' => '+8801700000001',
    'password' => bcrypt('password'),
    'status' => 'active',
]);
```

### "Route not found"

The route might not exist. Check your routes:

```bash
php artisan route:list
```

---

## 🎨 Color Output Explanation

| Color | Meaning |
|-------|---------|
| ✅ Green | Test passed, no issues |
| ⚠️ Yellow | Warning, non-critical issue |
| ❌ Red | Test failed, critical error |
| 🔵 Blue | Information/section header |

---

## 💡 Best Practices

### Before Commit

```bash
# Quick check
php artisan app:verify-all-pages

# Full check
./verify.sh
```

### Before Deployment

```bash
# Run all tests
./verify.sh

# Check GitHub Actions
# The workflow will run automatically on push
```

### Daily Maintenance

The GitHub Actions workflow runs daily at 2 AM UTC. You can also trigger it manually from the GitHub UI.

### Adding New Features

When you add new pages/resources:
1. Add the route to `tests/Feature/FullProjectVerificationTest.php`
2. Add Dusk tests if it's a UI feature
3. Run `./verify.sh` to verify everything works

---

## 📚 Command Reference

| Command | Description |
|---------|-------------|
| `./verify.sh` | Run full verification suite |
| `php artisan test` | Run all Pest tests |
| `php artisan test tests/Feature/FullProjectVerificationTest.php` | Run specific test |
| `php artisan app:verify-all-pages` | Verify all pages via HTTP |
| `vendor/bin/phpstan analyse` | Run static analysis |
| `php artisan dusk` | Run browser tests |
| `php artisan route:list` | List all routes |

---

## 🌟 Advanced Usage

### Test Specific Guard

```bash
# Test only admin routes
php artisan app:verify-all-pages --user=1 --customer=0

# Test only customer routes
php artisan app:verify-all-pages --user=0 --customer=6
```

### Generate JSON Report

```bash
php artisan app:verify-all-pages --output=json > verification-report.json
```

### Integrate with Other Tools

```bash
# Run verification and notify on failure
./verify.sh || echo "Verification failed!" | mail -s "Fiberloop Verification Failed" admin@example.com

# Run verification in CI
- name: Verify Project
  run: ./verify.sh
```

---

## 📞 Support

If you encounter issues with the verification system:

1. **Check the error message** - It usually tells you exactly what's wrong
2. **Check the logs** - `tail -n 50 storage/logs/laravel.log`
3. **Run individual tests** - Isolate which part is failing
4. **Check route exists** - `php artisan route:list | grep your-route`
5. **Verify user exists** - `php artisan tinker --execute="\App\Models\User::find(1)"`

---

## 🎉 Success Checklist

After running verification, check:

- [ ] All Pest tests pass
- [ ] PHPStan shows no errors (or only expected ones)
- [ ] Artisan command shows all routes passed
- [ ] No errors in `storage/logs/laravel.log`
- [ ] Dusk tests pass (if installed)

If all ✅, your project is verified and ready for deployment! 🚀

---

*Generated for Fiberloop Project*
*Last updated: 2026-08-08*
