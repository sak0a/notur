<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;

class RemoteKeyCommand extends Command
{
    protected $signature = 'notur:remote-key';

    protected $description = 'Generate a Notur remote push API key for SDK development workflows';

    public function handle(): int
    {
        $token = 'notur_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $this->info('Generated Notur remote push key:');
        $this->line($token);
        $this->newLine();
        $this->line('Add these values to your panel .env and clear config cache if needed:');
        $this->line('NOTUR_REMOTE_PUSH_ENABLED=true');
        $this->line('NOTUR_REMOTE_PUSH_KEYS=' . $token);
        $this->newLine();
        $this->warn('Anyone with this key can upload trusted PHP/JS extension code to this panel.');

        return self::SUCCESS;
    }
}
