<?php

return [
    // Where database backups are written. Point this at a second drive or a USB/cloud-synced folder.
    'backup_path' => env('BACKUP_PATH'),

    // How often the automatic backup runs, in days: 1 = daily, 7 = weekly.
    'backup_every_days' => max(1, (int) env('BACKUP_EVERY_DAYS', 7)),

    // Full path to mysqldump when using MySQL, e.g. C:\xampp\mysql\bin\mysqldump.exe
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),
];
