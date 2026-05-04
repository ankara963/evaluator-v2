<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('courses')
            ->where('semester', '>', 4)
            ->update(['semester' => 4]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
