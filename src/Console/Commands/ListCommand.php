<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\Models\InstalledExtension;

class ListCommand extends Command
{
    protected $signature = 'notur:list {--enabled : Show only enabled extensions} {--disabled : Show only disabled extensions}';
    protected $description = 'List installed Notur extensions';

    public function handle(): int
    {
        $query = InstalledExtension::query();

        if ($this->option('enabled')) {
            $query->where('enabled', true);
        } elseif ($this->option('disabled')) {
            $query->where('enabled', false);
        }

        $extensions = $query->get();

        if ($extensions->isEmpty()) {
            $this->info('No extensions installed.');
            return 0;
        }

        $this->table(
            ['ID', 'Name', 'Version', 'Status'],
            $extensions->map(fn ($ext) => [
                $ext->extension_id,
                $ext->name,
                $ext->version,
                $ext->enabled ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>',
            ])->toArray(),
        );

        return 0;
    }
}
