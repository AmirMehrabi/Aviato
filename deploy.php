<?php

namespace Deployer;

require 'recipe/laravel.php';

set('application', 'aviato');
set('repository', 'https://github.com/AmirMehrabi/Aviato.git');

set('keep_releases', 5);
set('default_timeout', 600);

set('composer_action', 'install');

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
    ->set('hostname', '5.202.19.75')
    ->set('remote_user', 'deploy')
    ->set('deploy_path', '/var/www/html/aviato')
    ->set('branch', 'master');

// Emergency-only host. Run it from /var/www/html/aviato, never from current/.
localhost('local')
    ->set('deploy_path', '/var/www/html/aviato')
    ->set('branch', 'master');

// Frontend build if package.json exists.
task('npm:build', function () {
    if (test('[ -f {{release_path}}/package.json ]')) {
        run('cd {{release_path}} && npm ci --no-audit --no-fund && npm run build && rm -rf node_modules');
    }
});

after('deploy:vendors', 'npm:build');

task('deploy:verify_release', function () {
    run('cd {{release_path}} && php artisan about --only=environment');
    run('test -f {{release_path}}/public/build/manifest.json');
});

before('deploy:publish', 'deploy:verify_release');

task('deploy:remember_current', function () {
    run('if [ -L {{current_path}} ]; then readlink -f {{current_path}} > {{deploy_path}}/.dep/previous_current; else rm -f {{deploy_path}}/.dep/previous_current; fi');
});

before('deploy:symlink', 'deploy:remember_current');

task('deploy:healthcheck', function () {
    try {
        run("curl --fail --silent --show-error --max-time 15 --header 'Host: aviato.ir' http://127.0.0.1/up > /dev/null");
        run('rm -f {{deploy_path}}/.dep/previous_current');
    } catch (\Throwable $exception) {
        run('if [ -s {{deploy_path}}/.dep/previous_current ]; then ln -sfn "$(cat {{deploy_path}}/.dep/previous_current)" {{current_path}}; fi');
        run('sudo -n /usr/bin/systemctl reload php8.3-fpm');

        throw $exception;
    }
});

after('deploy:symlink', 'deploy:healthcheck');

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
    run('sudo -n /usr/bin/systemctl reload php8.3-fpm');
});

// task('nginx:s3', function () {
//     $localPath = __DIR__.'/ops/nginx/aviato-s3.conf.example';
//     $remotePath = '/tmp/aviato-s3.conf';

//     upload($localPath, $remotePath);
//     run('sudo -n install -m 644 '.$remotePath.' /etc/nginx/sites-available/aviato-s3.conf');
//     run('sudo -n ln -sfn /etc/nginx/sites-available/aviato-s3.conf /etc/nginx/sites-enabled/aviato-s3.conf');
//     run('rm -f '.$remotePath);
//     run('sudo -n nginx -t');
//     run('sudo -n systemctl reload nginx');
// });

after('deploy:success', 'php-fpm:reload');

after('deploy:failed', 'deploy:unlock');
