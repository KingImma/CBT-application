<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo 'UniqueIdentifierGenerator bound: '.(app()->bound(UniqueIdentifierGenerator::class) ? 'true' : 'false')."\n";

use App\Models\Tenant;
use Illuminate\Contracts\Console\Kernel;
use Stancl\Tenancy\Contracts\UniqueIdentifierGenerator;

$tenant = Tenant::factory()->make();
echo 'Tenant ID from make: '.var_export($tenant->id, true)."\n";
echo 'Database column: '.var_export($tenant->database, true)."\n";
echo 'Tenant key: '.var_export($tenant->getTenantKey(), true)."\n";
echo 'Database name via getName(): '.var_export($tenant->database()->getName(), true)."\n";
