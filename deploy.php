<?php

namespace Deployer;

require 'recipe/laravel.php';

set('application', 'aviato');
set('repository', 'git@github.com:AmirMehrabi/Aviato.git');

set('keep_releases', 5);
set('default_timeout', 600);

// You asked for composer update on each deploy.
// Safer production default is "install", but this does what you asked.
set('composer_action', 'update');

set('composer_options', '--verbose --prefer-dist --no-progress --no-interaction --no-dev --optimize-autoloader');

set('shared_files', [
    '.env',
]);

set('shared_dirs', [
    'storage',
]);

set('writable_dirs', [
    'bootstrap/cache',
    'storage',
]);

set('writable_mode', 'acl');

// Used by GitHub Actions or your local computer.
// This connects to the production server by SSH.
host('production')
    ->set('hostname', '5.202.19.100')
    ->set('remote_user', 'deploy')
    ->set('deploy_path', '/var/www/html/aviato')
    ->set('branch', 'master');

// Used only when you manually run Deployer from the production server itself.
// Example: vendor/bin/dep deploy local -vvv
localhost('local')
    ->set('deploy_path', '/var/www/html/aviato')
    ->set('branch', 'master');

// Frontend build if package.json exists.
task('npm:build', function () {
    if (test('[ -f {{release_path}}/package.json ]')) {
        run('cd {{release_path}} && npm ci && npm run build');
    }
});

after('deploy:vendors', 'npm:build');

// Laravel recipe already runs php artisan migrate --force.
// Do not add another migration hook, or migrations may run twice.

task('supervisor:deploy', function () {
    $supervisorDir = '/etc/supervisor/conf.d';
    $programs = [
        'aviato-horizon.conf',
        'aviato-scheduler.conf',
    ];
    $legacyPrograms = [
        'aviato-queue-default.conf',
        'aviato-queue-deletions.conf',
        'aviato-queue-provisioning.conf',
        'aviato-queue-backups.conf',
        'aviato-queue-upgrades.conf',
    ];

    run('cd {{release_path}} && php artisan horizon:terminate');

    foreach ($legacyPrograms as $program) {
        run('sudo -n rm -f '.$supervisorDir.'/'.$program);
    }

    foreach ($programs as $program) {
        $localPath = __DIR__.'/ops/supervisor/'.$program;
        $remotePath = '/tmp/'.$program;

        upload($localPath, $remotePath);
        run('sudo -n install -m 644 '.$remotePath.' '.$supervisorDir.'/'.$program);
        run('rm -f '.$remotePath);
    }

    run('sudo -n supervisorctl reread');
    run('sudo -n supervisorctl update');
    run('sudo -n supervisorctl restart aviato-horizon:*');
    run('sudo -n supervisorctl restart aviato-scheduler:*');
});

after('deploy:success', 'supervisor:deploy');

task('php-fpm:reload', function () {
    run('sudo -n /usr/bin/systemctl reload php8.5-fpm');
});

after('deploy:success', 'php-fpm:reload');

after('deploy:failed', 'deploy:unlock');
