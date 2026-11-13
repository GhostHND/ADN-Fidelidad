<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('cover_path')->nullable();
            $t->string('primary_color', 7)->default('#0FA7B4');
            $t->string('secondary_color', 7)->default('#1F2937');
            $t->string('phone', 40)->nullable();
            $t->string('whatsapp', 40)->nullable();
            $t->string('email')->nullable();
            $t->string('address')->nullable();
            $t->string('website')->nullable();
            $t->json('social_links')->nullable();
            $t->json('opening_hours')->nullable();
            $t->string('locale', 5)->default('es');
            $t->string('timezone', 60)->default('America/Tegucigalpa');
            $t->string('currency', 3)->default('HNL');
            $t->text('privacy_notice')->nullable();
            $t->text('program_terms')->nullable();
            $t->string('status', 20)->default('active')->index(); // active|suspended
            $t->string('master_business_id')->nullable()->unique();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('business_branches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('address')->nullable();
            $t->string('phone', 40)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['business_id', 'is_active']);
        });

        Schema::create('business_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('business_branches')->nullOnDelete();
            $t->string('role', 20); // owner|manager|cashier
            $t->json('permissions')->nullable(); // lista explícita; null = valores del rol
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['business_id', 'user_id']);
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('name');
            $t->string('email')->nullable()->unique();
            $t->string('phone', 40)->nullable()->unique();
            $t->date('birthdate')->nullable();
            $t->timestamp('anonymized_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('customer_business', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('status', 20)->default('active'); // active|blocked
            $t->boolean('consent_privacy')->default(false);
            $t->boolean('consent_marketing')->default(false);
            $t->timestamp('consented_at')->nullable();
            $t->timestamp('last_activity_at')->nullable()->index();
            $t->timestamps();
            $t->unique(['business_id', 'customer_id']);
        });

        Schema::create('media_assets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 30); // logo|cover|hero|background|stamp_on|stamp_off|icon|reward
            $t->string('path');
            $t->string('mime', 60);
            $t->unsignedInteger('size');
            $t->unsignedInteger('width');
            $t->unsignedInteger('height');
            $t->string('original_name')->nullable();
            $t->timestamps();
            $t->index(['business_id', 'kind']);
        });

        Schema::create('loyalty_programs', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('status', 20)->default('draft'); // draft|active|paused|archived
            $t->unsignedInteger('stamps_goal')->default(8);
            $t->string('rule_type', 20)->default('per_purchase'); // per_purchase|per_amount|per_service|promotional
            $t->decimal('amount_per_stamp', 12, 2)->nullable();
            $t->unsignedInteger('max_stamps_per_operation')->default(5);
            $t->string('cycle_policy', 20)->default('reset'); // reset|continue|keep_remainder
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->text('terms')->nullable();
            $t->json('design'); // vigente: colores, textos, ids de recursos
            $t->unsignedInteger('current_version')->default(1);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->index(['business_id', 'status']);
        });

        Schema::create('program_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot'); // reglas + diseño
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('change_note')->nullable();
            $t->json('platform_notes')->nullable();
            $t->timestamps();
            $t->unique(['program_id', 'version']);
        });

        Schema::create('stamp_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $t->string('name');
            $t->string('type', 20); // service|promotional
            $t->unsignedInteger('stamps')->default(1);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['program_id', 'is_active']);
        });

        Schema::create('rewards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('type', 20)->default('free_product'); // free_product|free_service|percent_discount|fixed_discount|special|custom
            $t->decimal('value', 12, 2)->nullable();
            $t->unsignedInteger('stamps_required');
            $t->boolean('consumes_stamps')->default(false);
            $t->text('terms')->nullable();
            $t->timestamp('valid_from')->nullable();
            $t->timestamp('valid_until')->nullable();
            $t->unsignedInteger('max_redemptions')->nullable();
            $t->string('redemption_policy')->nullable();
            $t->string('status', 20)->default('active'); // active|inactive|archived
            $t->foreignId('image_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $t->timestamps();
            $t->index(['program_id', 'status']);
        });

        Schema::create('customer_cards', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('qr_token', 64)->unique(); // identificador opaco de alta entropía
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $t->unsignedInteger('program_version')->default(1);
            $t->unsignedInteger('stamps_balance')->default(0);
            $t->unsignedInteger('lifetime_stamps')->default(0);
            $t->unsignedInteger('cycle_number')->default(1);
            $t->string('status', 20)->default('active'); // active|blocked|expired
            $t->timestamp('last_activity_at')->nullable();
            $t->unsignedInteger('revision')->default(1); // sube con cada cambio visible en Wallet
            $t->timestamps();
            $t->unique(['customer_id', 'program_id']);
            $t->index(['business_id', 'status']);
        });

        Schema::create('loyalty_cycles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('card_id')->constrained('customer_cards')->cascadeOnDelete();
            $t->unsignedInteger('number');
            $t->unsignedInteger('stamps')->default(0);
            $t->timestamp('started_at');
            $t->timestamp('completed_at')->nullable();
            $t->unique(['card_id', 'number']);
        });

        Schema::create('stamp_transactions', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('business_branches')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('card_id')->constrained('customer_cards')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $t->string('type', 20); // earn|reverse|adjust|consume
            $t->integer('stamps');
            $t->unsignedInteger('balance_after');
            $t->unsignedInteger('cycle_number');
            $t->decimal('purchase_amount', 12, 2)->nullable();
            $t->string('idempotency_key', 100)->unique();
            $t->string('reference')->nullable();
            $t->string('reason')->nullable();
            $t->foreignId('reverses_id')->nullable()->constrained('stamp_transactions')->nullOnDelete();
            $t->timestamps();
            $t->index(['business_id', 'created_at']);
            $t->index(['card_id', 'created_at']);
            $t->index(['user_id', 'created_at']);
            $t->index(['branch_id', 'created_at']);
        });

        Schema::create('reward_entitlements', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('card_id')->constrained('customer_cards')->cascadeOnDelete();
            $t->foreignId('reward_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('cycle_number');
            $t->string('status', 20)->default('available'); // available|redeemed|expired|void
            $t->timestamp('earned_at');
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->unique(['card_id', 'reward_id', 'cycle_number']);
            $t->index(['business_id', 'status']);
        });

        Schema::create('reward_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('entitlement_id')->unique()->constrained('reward_entitlements')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('business_branches')->nullOnDelete();
            $t->timestamp('redeemed_at');
            $t->timestamps();
            $t->index(['business_id', 'redeemed_at']);
        });

        Schema::create('wallet_passes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('card_id')->constrained('customer_cards')->cascadeOnDelete();
            $t->string('platform', 10); // apple|google
            $t->string('identifier')->unique(); // serial Apple / id de objeto Google
            $t->string('auth_token', 64)->nullable(); // Apple authenticationToken
            $t->string('status', 20)->default('generated'); // generated|link_opened|installed_verified|failed
            $t->unsignedInteger('synced_revision')->default(0);
            $t->timestamp('last_synced_at')->nullable();
            $t->timestamp('link_opened_at')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->unique(['card_id', 'platform']);
        });

        Schema::create('wallet_sync_jobs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('card_id')->nullable()->constrained('customer_cards')->cascadeOnDelete();
            $t->foreignId('program_id')->nullable()->constrained('loyalty_programs')->cascadeOnDelete();
            $t->string('platform', 10); // apple|google
            $t->string('scope', 10)->default('object'); // class|object
            $t->string('reason', 40);
            $t->string('status', 20)->default('pending'); // pending|done|failed|skipped
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('next_attempt_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'next_attempt_at']);
        });

        Schema::create('apple_wallet_devices', function (Blueprint $t) {
            $t->id();
            $t->string('device_library_id')->unique();
            $t->string('push_token');
            $t->timestamps();
        });

        Schema::create('apple_wallet_registrations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('device_id')->constrained('apple_wallet_devices')->cascadeOnDelete();
            $t->foreignId('pass_id')->constrained('wallet_passes')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['device_id', 'pass_id']);
        });

        Schema::create('subscription_entitlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('product_code', 40)->default('adn-fidelidad');
            $t->string('subscription_id')->nullable();
            $t->string('plan')->nullable();
            $t->string('status', 20)->default('active'); // active|trial|past_due|suspended|expired|cancelled
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->json('features')->nullable();
            $t->json('limits')->nullable();
            $t->unsignedBigInteger('event_version')->default(0);
            $t->timestamp('last_synced_at')->nullable();
            $t->timestamps();
        });

        Schema::create('master_sync_events', function (Blueprint $t) {
            $t->id();
            $t->string('event_id', 100)->unique(); // idempotencia
            $t->string('type', 60);
            $t->string('master_business_id')->nullable()->index();
            $t->unsignedBigInteger('event_version')->nullable();
            $t->string('status', 20); // applied|ignored|failed|rejected
            $t->json('payload')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 80)->index();
            $t->string('subject_type', 80)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('meta')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['business_id', 'created_at']);
            $t->index(['subject_type', 'subject_id']);
        });

        Schema::create('idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->string('key', 100)->unique();
            $t->string('scope', 40);
            $t->unsignedSmallInteger('response_status')->nullable();
            $t->json('response_body')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'idempotency_keys', 'audit_logs', 'master_sync_events', 'subscription_entitlements',
            'apple_wallet_registrations', 'apple_wallet_devices', 'wallet_sync_jobs', 'wallet_passes',
            'reward_redemptions', 'reward_entitlements', 'stamp_transactions', 'loyalty_cycles', 'customer_cards',
            'rewards', 'stamp_rules', 'program_versions', 'loyalty_programs', 'media_assets',
            'customer_business', 'customers', 'business_user', 'business_branches', 'businesses',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
