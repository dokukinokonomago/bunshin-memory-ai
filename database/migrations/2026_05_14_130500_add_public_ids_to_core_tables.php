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
        $this->addPublicId('tenants', 'ten');
        $this->addPublicId('users', 'usr');
        $this->addPublicId('categories', 'cat');
        $this->addPublicId('memories', 'mem');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropPublicId('memories');
        $this->dropPublicId('categories');
        $this->dropPublicId('users');
        $this->dropPublicId('tenants');
    }

    private function addPublicId(string $table, string $prefix): void
    {
        Schema::table($table, static function (Blueprint $table): void {
            $table->string('public_id', 30)->nullable()->after('id')->unique();
        });

        DB::table($table)
            ->whereNull('public_id')
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(100, function ($rows) use ($table, $prefix): void {
                foreach ($rows as $row) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update([
                            'public_id' => $prefix.'_'.(string) Str::ulid(),
                        ]);
                }
            });
    }

    private function dropPublicId(string $tableName): void
    {
        Schema::table($tableName, static function (Blueprint $table) use ($tableName): void {
            $table->dropUnique($tableName.'_public_id_unique');
            $table->dropColumn('public_id');
        });
    }
};
