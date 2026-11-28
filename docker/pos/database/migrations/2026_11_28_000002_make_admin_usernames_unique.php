<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('admin_users')
            ->select('username', DB::raw('COUNT(*) as total'))
            ->groupBy('username')
            ->having('total', '>', 1)
            ->pluck('username')
            ->all();

        if ($duplicates) {
            throw new RuntimeException(
                'Duplicate staff usernames must be fixed before this migration can run: '.implode(', ', $duplicates)
            );
        }

        Schema::table('admin_users', function (Blueprint $table): void {
            $table->unique('username', 'admin_users_username_unique');
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table): void {
            $table->dropUnique('admin_users_username_unique');
        });
    }
};
