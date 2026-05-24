<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenant_member_invitations', static function (Blueprint $table): void {
            $table->string('public_id', 30)->nullable()->after('id')->unique();
        });

        DB::table('tenant_member_invitations')
            ->whereNull('public_id')
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('tenant_member_invitations')
                        ->where('id', $row->id)
                        ->update([
                            'public_id' => 'inv_'.(string) Str::ulid(),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_member_invitations', static function (Blueprint $table): void {
            $table->dropUnique('tenant_member_invitations_public_id_unique');
            $table->dropColumn('public_id');
        });
    }
};
