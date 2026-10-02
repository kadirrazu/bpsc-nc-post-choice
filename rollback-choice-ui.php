<?php
// Run from the project root after replacing the rollback files.
foreach ([
    'app/Http/Controllers/ChoiceDataResetController.php',
    'resources/views/choice/events/reset.blade.php',
    'resources/views/choice/candidate/status.blade.php',
] as $relative) {
    $path=__DIR__.'/'.$relative;
    if (is_file($path) && !unlink($path)) throw new RuntimeException('Cannot remove unused patch file: '.$relative);
}
echo "Unused files from the superseded patch removed. No database data was changed.\n";
