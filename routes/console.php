<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Recovery is restricted to an unrecorded migration with entirely empty tables.
Artisan::command('choice:repair-phase1', function () {
    $migration = '2026_09_30_000001_create_choice_foundation';
    $schema = \Illuminate\Support\Facades\Schema::getFacadeRoot();
    $db = \Illuminate\Support\Facades\DB::connection();
    if ($schema->hasTable('migrations') && $db->table('migrations')->where('migration', $migration)->exists()) {
        $this->error('Refused: the Phase 1 migration is already recorded as completed.');
        return 1;
    }
    $tables = ['choice_audits', 'choice_submission_items', 'choice_submissions',
        'candidate_imports', 'candidate_applications', 'event_candidates',
        'choice_options', 'event_posts', 'choice_events'];
    // Check every table before dropping anything. Never remove populated tables.
    foreach ($tables as $table) {
        if ($schema->hasTable($table) && $db->table($table)->exists()) {
            $this->error('Refused: '.$table.' contains data. No tables were removed.');
            return 1;
        }
    }
    // Dependency order permits cleanup without disabling foreign-key checks.
    foreach ($tables as $table) {
        if ($schema->hasTable($table)) {
            $schema->drop($table);
            $this->line('Removed empty table: '.$table);
        }
    }
    $this->info('Empty partial Phase 1 tables cleared. Run php artisan migrate.');
    return 0;
})->purpose('Remove only empty tables left by an incomplete Phase 1 migration');
