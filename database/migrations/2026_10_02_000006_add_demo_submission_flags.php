<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('event_candidates',fn(Blueprint $t)=>$t->boolean('is_demo')->default(false)->index());
        Schema::table('choice_submissions',fn(Blueprint $t)=>$t->boolean('is_demo')->default(false)->index());
    }
    public function down(): void {
        Schema::table('choice_submissions',fn(Blueprint $t)=>$t->dropColumn('is_demo'));
        Schema::table('event_candidates',fn(Blueprint $t)=>$t->dropColumn('is_demo'));
    }
};
