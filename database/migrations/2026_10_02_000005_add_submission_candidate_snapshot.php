<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('choice_submissions',fn(Blueprint $t)=>$t->json('candidate_snapshot')->nullable()); }
    public function down(): void { Schema::table('choice_submissions',fn(Blueprint $t)=>$t->dropColumn('candidate_snapshot')); }
};
