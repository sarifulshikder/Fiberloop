<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed roles and permissions after every database refresh so that
     * any test that calls assignRole() or uses Spatie permission checks
     * does not fail with "relation roles does not exist" or
     * "There is no role named X".
     *
     * Only runs when the RefreshDatabase trait is in use (i.e. the DB was
     * actually wiped/reset for this test). Otherwise the seeder would run
     * repeatedly for non-RefreshDatabase tests, which is wasteful.
     */
    protected bool $seed = false;

    protected string $seeder = RolesAndPermissionsSeeder::class;
}
