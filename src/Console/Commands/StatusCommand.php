<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\Console\UI\Components\StatusDashboard;
use Notur\Console\UI\Concerns\HasInteractiveUI;
use Notur\ExtensionManager;

/**
 * Display Notur system status dashboard.
 *
 * Shows system health, installed extensions, and available updates
 * in a beautiful terminal dashboard format.
 */
class StatusCommand extends Command
{
    use HasInteractiveUI;

    protected $signature = 'notur:status
        {--json : Output as JSON}
        {--health : Show health checks only}
        {--extensions : Show extensions only}
        {--compact : Compact output format}';

    protected $description = 'Display Notur system status and health dashboard';

    public function __construct(
        private readonly ExtensionManager $manager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dashboard = new StatusDashboard($this, $this->manager);

        // JSON output
        if ($this->option('json')) {
            $this->line(json_encode($dashboard->toArray(), JSON_PRETTY_PRINT));

            return 0;
        }

        // Partial outputs
        if ($this->option('health')) {
            $dashboard->renderSystemHealth();

            return 0;
        }

        if ($this->option('extensions')) {
            $dashboard->renderExtensions();

            return 0;
        }

        // Full dashboard
        $dashboard->render();

        return 0;
    }
}
