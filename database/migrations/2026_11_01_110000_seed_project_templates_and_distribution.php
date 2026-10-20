<?php

use Database\Seeders\ProjectTemplatesSeeder;
use Database\Seeders\ProjectsServiceDistributionSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * The stage plans and the trades that get «متابعة المشاريع» travel with the schema: a seeder nobody wires into the pipeline is
 * forgotten (see ChildServiceScopeSeeder). Both seeders are idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ProjectTemplatesSeeder())->run();
        (new ProjectsServiceDistributionSeeder())->run();
    }

    public function down(): void
    {
        // data only; nothing to undo safely
    }
};
