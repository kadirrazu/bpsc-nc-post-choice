<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('choice_events', function (Blueprint $t) {
            $t->id(); $t->string('title'); $t->text('instructions')->nullable();
            $t->string('status', 16)->default('DRAFT');
            $t->dateTime('start_at'); $t->dateTime('end_at')->index();
            $t->boolean('multiple_posts')->default(false);
            $t->foreignId('created_by')->constrained('users'); $t->timestamps();
            $t->index(['status', 'start_at', 'end_at']);
        });
        Schema::create('event_posts', function (Blueprint $t) {
            $t->id(); $t->foreignId('choice_event_id')->constrained();
            $t->string('post_code', 20); $t->string('title');
            $t->string('organization')->nullable(); $t->string('ministry')->nullable();
            $t->timestamps(); $t->unique(['choice_event_id', 'post_code']);
        });
        Schema::create('choice_options', function (Blueprint $t) {
            $t->id(); $t->foreignId('choice_event_id')->constrained();
            $t->foreignId('event_post_id')->constrained();
            $t->string('code', 20); $t->string('title');
            $t->unsignedInteger('post_count')->nullable(); $t->unsignedInteger('sort_order');
            $t->timestamps(); $t->unique(['choice_event_id', 'code']);
        });
        Schema::create('event_candidates', function (Blueprint $t) {
            $t->id(); $t->foreignId('choice_event_id')->constrained();
            $t->string('name'); $t->string('fname'); $t->string('mname'); $t->date('b_date');
            foreach (['ssc_roll', 'ssc_year', 'hsc_roll', 'hsc_year', 'nid'] as $field) $t->string($field, 30)->nullable();
            $t->timestamps(); $t->index(['choice_event_id', 'b_date']);
        });
        Schema::create('candidate_applications', function (Blueprint $t) {
            $t->id(); $t->foreignId('event_candidate_id')->constrained();
            $t->foreignId('event_post_id')->constrained();
            $t->string('user', 10); $t->string('reg', 10);
            foreach (['dist_code', 'dist_name', 'unit', 'post_code', 'post_name', 'ministry'] as $field) $t->string($field)->nullable();
            $t->timestamps(); $t->unique(['event_post_id', 'user']); $t->unique(['event_post_id', 'reg']);
        });
        Schema::create('candidate_imports', function (Blueprint $t) {
            $t->id(); $t->foreignId('event_post_id')->constrained();
            $t->string('filename'); $t->unsignedInteger('row_count');
            $t->foreignId('imported_by')->constrained('users'); $t->timestamps();
        });
        Schema::create('choice_submissions', function (Blueprint $t) {
            $t->id(); $t->foreignId('event_candidate_id')->constrained();
            $t->text('submitted_choices'); $t->text('unselected_choices')->nullable();
            $t->string('token', 32)->unique(); $t->string('status', 16)->default('SUBMITTED');
            $t->dateTime('submitted_at'); $t->string('submitted_ip', 45);
            $t->foreignId('cancelled_by')->nullable()->constrained('users');
            $t->dateTime('cancelled_at')->nullable(); $t->text('cancellation_reason')->nullable(); $t->timestamps();
        });
        Schema::create('choice_submission_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('choice_submission_id')->constrained();
            $t->foreignId('choice_option_id')->constrained(); $t->unsignedInteger('preference_order');
            $t->unique(['choice_submission_id', 'choice_option_id'], 'csi_submission_option_uq');
            $t->unique(['choice_submission_id', 'preference_order'], 'csi_submission_order_uq');
        });
        Schema::create('choice_audits', function (Blueprint $t) {
            $t->id(); $t->foreignId('choice_event_id')->constrained();
            $t->foreignId('actor_id')->constrained('users'); $t->string('action');
            $t->json('details')->nullable(); $t->timestamps();
        });
    }
    public function down(): void {
        foreach (['choice_audits','choice_submission_items','choice_submissions','candidate_imports','candidate_applications','event_candidates','choice_options','event_posts','choice_events'] as $table) Schema::dropIfExists($table);
    }
};
