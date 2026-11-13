<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_wallet_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_pass_id')->constrained('wallet_passes')->cascadeOnDelete();
            $table->foreignId('stamp_transaction_id')->constrained('stamp_transactions')->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('status', 20)->default('pending');
            $table->timestamp('attempted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['wallet_pass_id', 'stamp_transaction_id'], 'google_notification_pass_transaction_unique');
            $table->index(['status', 'business_id']);
            $table->index(['wallet_pass_id', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_wallet_notifications');
    }
};
