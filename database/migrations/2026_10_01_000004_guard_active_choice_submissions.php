<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up(): void {
        if (DB::table('choice_submissions')->select('event_candidate_id')->where('status','SUBMITTED')->groupBy('event_candidate_id')->havingRaw('COUNT(*) > 1')->first() !== null) throw new RuntimeException('Duplicate active submissions must be resolved before migration.');
        Schema::table('choice_submissions',fn(Blueprint $t)=>$t->unsignedTinyInteger('active_slot')->nullable());
        DB::table('choice_submissions')->where('status','SUBMITTED')->update(['active_slot'=>1]);
        Schema::table('choice_submissions',fn(Blueprint $t)=>$t->unique(['event_candidate_id','active_slot'],'cs_candidate_active_uq'));
    }
    public function down(): void {
        Schema::table('choice_submissions',function (Blueprint $t) { $t->dropUnique('cs_candidate_active_uq'); $t->dropColumn('active_slot'); });
    }
};
