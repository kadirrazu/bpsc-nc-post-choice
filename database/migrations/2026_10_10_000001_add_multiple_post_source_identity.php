<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('candidate_applications',fn(Blueprint $table)=>$table->json('source_identity')->nullable()); }
    public function down(): void { Schema::table('candidate_applications',fn(Blueprint $table)=>$table->dropColumn('source_identity')); }
};
