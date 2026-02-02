<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\Support\SignatureVerifier;

class KeygenCommand extends Command
{
    protected $signature = 'notur:keygen';

    protected $description = 'Generate a new Ed25519 keypair for signing extensions';

    public function handle(SignatureVerifier $verifier): int
    {
        $keypair = $verifier->generateKeypair();

        $this->newLine();
        $this->info('Ed25519 keypair generated successfully.');
        $this->newLine();

        $this->line('<comment>Public Key:</comment>');
        $this->line($keypair['public']);
        $this->newLine();

        $this->line('<comment>Secret Key:</comment>');
        $this->line($keypair['secret']);
        $this->newLine();

        $this->warn('Store the secret key securely. It is used to sign extension archives.');
        $this->info('Add the public key to your panel configuration:');
        $this->newLine();

        $this->line("  NOTUR_PUBLIC_KEY={$keypair['public']}");
        $this->newLine();

        $this->info('To sign an archive, set the secret key as an environment variable:');
        $this->newLine();

        $this->line("  NOTUR_SECRET_KEY={$keypair['secret']}");
        $this->line('  php artisan notur:export --sign');
        $this->newLine();

        return 0;
    }
}
