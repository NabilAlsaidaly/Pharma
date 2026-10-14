<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('management_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('display_name', 150);
            $table->string('email', 254)->unique();
            $table->string('password');
            $table->string('role', 32)->default('owner');
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->unsignedBigInteger('credential_version')->default(1);
            $table->unsignedTinyInteger('owner_slot')->nullable()->storedAs("CASE WHEN role = 'owner' THEN 1 ELSE NULL END")->unique();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE management_users ADD CONSTRAINT management_credential_version_check CHECK (credential_version >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('management_users');
    }
};
