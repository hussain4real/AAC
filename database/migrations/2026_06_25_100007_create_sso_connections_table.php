<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enterprise identity connections: an OAuth 2.0 / OIDC provider a team's web
 * users authenticate through. The connection carries the provider endpoints, the
 * client credentials (the secret encrypted at rest), the claim mapping, and the
 * group→role rules that map an external identity onto MAACC team/project roles.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sso_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('provider')->default('oidc');
            $table->string('authorize_url');
            $table->string('token_url');
            $table->string('userinfo_url');
            $table->string('client_id');
            $table->text('client_secret')->nullable();
            $table->string('scopes')->default('openid profile email');
            $table->string('email_claim')->default('email');
            $table->string('name_claim')->default('name');
            $table->string('groups_claim')->default('groups');
            $table->string('default_team_role')->default('member');
            $table->json('group_role_mappings')->nullable();
            $table->boolean('auto_provision')->default(true);
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sso_connections');
    }
};
