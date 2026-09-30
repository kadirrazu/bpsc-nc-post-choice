<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up(): void {
        Schema::table('choice_events', function (Blueprint $table) {
            $table->string('post_code',20)->nullable();
            $table->string('unit',32)->nullable();
        });
        DB::table('choice_events')->where('status','OPEN')->update(['status'=>'PUBLISHED']);
    }
    public function down(): void {
        DB::table('choice_events')->where('status','PUBLISHED')->update(['status'=>'OPEN']);
        Schema::table('choice_events', fn(Blueprint $table)=>$table->dropColumn(['post_code','unit']));
    }
};
