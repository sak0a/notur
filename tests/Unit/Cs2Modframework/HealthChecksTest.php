<?php

declare(strict_types=1);

namespace Notur\Tests\Unit\Cs2Modframework;

use Notur\Contracts\HasHealthChecks;
use Notur\Cs2Modframework\Cs2ModframeworkExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . '/../../../extensions/cs2-modframework/src/Cs2ModframeworkExtension.php';

class HealthChecksTest extends TestCase
{
    public function test_extension_declares_and_reports_admin_health_checks(): void
    {
        $manifest = Yaml::parseFile(__DIR__ . '/../../../extensions/cs2-modframework/extension.yaml');
        $definitions = $manifest['health']['checks'] ?? [];
        $definitionIds = array_column($definitions, 'id');

        $this->assertContains('frontend_bundle', $definitionIds);
        $this->assertContains('api_routes', $definitionIds);
        $this->assertContains('pterodactyl_runtime', $definitionIds);
        $this->assertContains('cache_store', $definitionIds);
        $this->assertContains('manifest_contract', $definitionIds);

        $extension = new Cs2ModframeworkExtension();

        $this->assertInstanceOf(HasHealthChecks::class, $extension);

        $results = $extension->getHealthChecks();
        $resultIds = array_column($results, 'id');

        foreach ($definitionIds as $definitionId) {
            $this->assertContains($definitionId, $resultIds);
        }

        $resultMap = [];
        foreach ($results as $result) {
            $resultMap[$result['id']] = $result;
        }

        $this->assertSame('ok', $resultMap['frontend_bundle']['status']);
        $this->assertSame('ok', $resultMap['api_routes']['status']);
        $this->assertSame('ok', $resultMap['manifest_contract']['status']);
    }
}
