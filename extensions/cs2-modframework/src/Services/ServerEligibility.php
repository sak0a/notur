<?php

declare(strict_types=1);

namespace Notur\Cs2Modframework\Services;

use Pterodactyl\Models\Server;

class ServerEligibility
{
    /**
     * @return array{supported: bool, reason: string|null, signals: array<string, string>}
     */
    public function check(Server $server): array
    {
        $signals = $this->signals($server);
        $haystack = strtolower(implode(' ', array_values($signals)));

        $strongPatterns = [
            'counter-strike 2',
            'counter strike 2',
            'counterstrike2',
            'cs2',
            'csgo',
            'game/csgo',
            'steam_appid 730',
            'app_update 730',
        ];

        foreach ($strongPatterns as $pattern) {
            if (str_contains($haystack, $pattern)) {
                return [
                    'supported' => true,
                    'reason' => null,
                    'signals' => $signals,
                ];
            }
        }

        return [
            'supported' => false,
            'reason' => 'This server does not look like a Counter-Strike 2 server. Rename the egg or startup metadata to include CS2/csgo if this is a custom CS2 egg.',
            'signals' => $signals,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function signals(Server $server): array
    {
        $signals = [
            'server_name' => (string) ($server->name ?? ''),
            'startup' => (string) ($server->startup ?? ''),
            'image' => (string) ($server->image ?? ''),
        ];

        try {
            $egg = $server->relationLoaded('egg') ? $server->getRelation('egg') : $server->egg;
            if ($egg !== null) {
                $signals['egg_name'] = (string) ($egg->name ?? '');
                $signals['egg_description'] = (string) ($egg->description ?? '');
                $signals['egg_startup'] = (string) ($egg->startup ?? '');
            }
        } catch (\Throwable) {
            // Eligibility should be conservative if egg metadata is unavailable.
        }

        return array_filter($signals, fn (string $value): bool => $value !== '');
    }
}
