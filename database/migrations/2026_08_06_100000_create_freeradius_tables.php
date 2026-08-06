<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = Schema::connection('radius');

        if (!$connection->hasTable('radcheck')) {
            $connection->create('radcheck', function (Blueprint $table) {
                $table->id();
                $table->string('username', 64)->default('');
                $table->string('attribute', 64)->default('');
                $table->char('op', 2)->default('==');
                $table->string('value', 253)->default('');
                $table->timestamps();

                $table->index('username');
            });
        }

        if (!$connection->hasTable('radreply')) {
            $connection->create('radreply', function (Blueprint $table) {
                $table->id();
                $table->string('username', 64)->default('');
                $table->string('attribute', 64)->default('');
                $table->char('op', 2)->default('=');
                $table->string('value', 253)->default('');
                $table->timestamps();

                $table->index('username');
            });
        }

        if (!$connection->hasTable('radgroupcheck')) {
            $connection->create('radgroupcheck', function (Blueprint $table) {
                $table->id();
                $table->string('groupname', 64)->default('');
                $table->string('attribute', 64)->default('');
                $table->char('op', 2)->default('==');
                $table->string('value', 253)->default('');
                $table->timestamps();

                $table->index('groupname');
            });
        }

        if (!$connection->hasTable('radgroupreply')) {
            $connection->create('radgroupreply', function (Blueprint $table) {
                $table->id();
                $table->string('groupname', 64)->default('');
                $table->string('attribute', 64)->default('');
                $table->char('op', 2)->default('=');
                $table->string('value', 253)->default('');
                $table->timestamps();

                $table->index('groupname');
            });
        }

        if (!$connection->hasTable('radusergroup')) {
            $connection->create('radusergroup', function (Blueprint $table) {
                $table->id();
                $table->string('username', 64)->default('');
                $table->string('groupname', 64)->default('');
                $table->integer('priority')->default(1);
                $table->timestamps();

                $table->index('username');
            });
        }

        if (!$connection->hasTable('radacct')) {
            $connection->create('radacct', function (Blueprint $table) {
                $table->id('radacctid');
                $table->string('acctsessionid', 64)->default('');
                $table->string('acctuniqueid', 32)->default('');
                $table->string('username', 64)->default('');
                $table->string('realm', 64)->nullable()->default('');
                $table->string('nasipaddress', 45)->default('');
                $table->string('nasportid', 32)->nullable();
                $table->string('nasporttype', 32)->nullable();
                $table->dateTime('acctstarttime')->nullable();
                $table->dateTime('acctupdatetime')->nullable();
                $table->dateTime('acctstoptime')->nullable();
                $table->bigInteger('acctinterval')->nullable();
                $table->bigInteger('acctsessiontime')->nullable();
                $table->string('acctauthentic', 32)->nullable();
                $table->string('connectinfo_start', 50)->nullable();
                $table->string('connectinfo_stop', 50)->nullable();
                $table->bigInteger('acctinputoctets')->nullable();
                $table->bigInteger('acctoutputoctets')->nullable();
                $table->string('calledstationid', 50)->default('');
                $table->string('callingstationid', 50)->default('');
                $table->string('acctterminatecause', 32)->default('');
                $table->string('servicetype', 32)->nullable();
                $table->string('framedprotocol', 32)->nullable();
                $table->string('framedipaddress', 45)->nullable();
                $table->string('framedipv6address', 45)->nullable();
                $table->string('framedipv6prefix', 45)->nullable();
                $table->string('framedinterfaceid', 44)->nullable();
                $table->string('delegatedipv6prefix', 45)->nullable();
                $table->timestamps();

                $table->index('username');
                $table->index('acctuniqueid');
                $table->index('nasipaddress');
                $table->index('acctstarttime');
                $table->index('acctstoptime');
            });
        }

        if (!$connection->hasTable('nas')) {
            $connection->create('nas', function (Blueprint $table) {
                $table->id();
                $table->string('nasname', 128);
                $table->string('shortname', 32)->nullable();
                $table->string('type', 30)->default('other');
                $table->integer('ports')->nullable();
                $table->string('secret', 255);
                $table->string('server', 64)->nullable();
                $table->string('community', 50)->nullable();
                $table->string('description', 200)->default('RADIUS Client');
                $table->timestamps();

                $table->index('nasname');
            });
        }

        if (!$connection->hasTable('radpostauth')) {
            $connection->create('radpostauth', function (Blueprint $table) {
                $table->id();
                $table->string('username', 64)->default('');
                $table->string('pass', 64)->default('');
                $table->string('reply', 32)->default('');
                $table->dateTime('authdate')->useCurrent();
                $table->string('class', 64)->nullable();
                $table->timestamps();

                $table->index('username');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = Schema::connection('radius');

        $connection->dropIfExists('radpostauth');
        $connection->dropIfExists('nas');
        $connection->dropIfExists('radacct');
        $connection->dropIfExists('radusergroup');
        $connection->dropIfExists('radgroupreply');
        $connection->dropIfExists('radgroupcheck');
        $connection->dropIfExists('radreply');
        $connection->dropIfExists('radcheck');
    }
};
