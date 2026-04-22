<?php

declare(strict_types=1);

use App\Enums\StatusType;
use App\Enums\SchoolType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    public function up(): void
    {
        Schema::create("tenants", function (Blueprint $table) {
            // ID is the tenant slug (e.g. "kings-college-lagos"), set explicitly
            // in CreateTenantAction. Using string instead of uuid because slugs
            // are not UUID-format and PostgreSQL's uuid type would reject them.
            $table->string("id", 63)->primary();

            $table->string("name");
            $table->string("slug", 63)->unique();
            $table->string('handle', 100)->nullable()->unique()->after('slug');
            $table->enum('school_type', array_column(SchoolType::cases(), 'value'))->nullable()->after('name');
            $table->string("database", 63)->unique();

            $table->string("logo", 500)->nullable();
            $table->text("address")->nullable();
            $table->string("city", 100)->nullable();
            $table->string("state", 50)->nullable();
            $table->string("phone", 20)->nullable();
            $table->string("email")->nullable();

            // plan_id references subscription_plans.id which uses UUID.
            $table
                ->foreignUuid("plan_id")
                ->nullable()
                ->constrained("subscription_plans");

            $table->enum(
                "subscription_status",
                array_column(StatusType::cases(), "value"),
            );
            $table->timestamp("trial_ends_at")->nullable();
            $table->timestamp("subscription_ends_at")->nullable();

            // Stores transient provisioning data (e.g. onboarding_admin credentials
            // written by CreateTenantAction and consumed + cleared by TenantDatabaseSeeder).
            $table->jsonb("settings")->nullable();

            $table->boolean("is_active")->default(true);

            // Set by the onboarding flow once the school has finished initial setup.
            $table->timestamp("onboarding_completed_at")->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Used internally by stancl/tenancy to store extra tenant attributes
            // that are not mapped to custom columns.
            $table->json("data")->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("tenants");
    }
}
