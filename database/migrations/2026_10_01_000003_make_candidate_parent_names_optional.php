<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up(): void {
        Schema::table('event_candidates',function (Blueprint $table) {
            $table->string('fname')->nullable()->change();
            $table->string('mname')->nullable()->change();
        });
    }
    public function down(): void {
        if (DB::table('event_candidates')->whereNull('fname')->orWhereNull('mname')->exists()) throw new RuntimeException('Cannot restore required parent names while missing values exist.');
        Schema::table('event_candidates',function (Blueprint $table) {
            $table->string('fname')->nullable(false)->change();
            $table->string('mname')->nullable(false)->change();
        });
    }
};
