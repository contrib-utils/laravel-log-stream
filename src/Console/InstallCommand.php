<?php

namespace LogScope\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'logscope:install {--force : Overwrite any existing published files}';

    protected $description = 'Publish LogScope configuration and assets, and print the env keys to set.';

    public function handle(): int
    {
        $this->components->info('Installing LogScope…');

        $this->callSilently('vendor:publish', array_filter([
            '--tag' => 'logscope-config',
            '--force' => $this->option('force'),
        ]));
        $this->components->task('Published config (config/logscope.php)');

        $this->callSilently('vendor:publish', array_filter([
            '--tag' => 'logscope-assets',
            '--force' => $this->option('force'),
        ]));
        $this->components->task('Published compiled assets');

        $this->newLine();
        $this->components->info('Set these environment variables to protect the dashboard:');
        $this->line('  <fg=yellow>LOGSCOPE_AUTH_USER</>=admin');
        $this->line('  <fg=yellow>LOGSCOPE_AUTH_PASSWORD</>=change-me');
        $this->newLine();
        $this->line('  Then visit <fg=cyan>/'.trim(config('logscope.route_prefix'), '/').'</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
