<?php

namespace Tests\Feature\Scenarios;

use App\Enums\ResponseFormat;
use App\Models\Scenario;
use App\Scenarios\ScenarioDefinition;
use Tests\Concerns\LoadsSchema;
use Tests\TestCase;

class ScenarioSyncTest extends TestCase
{
    use LoadsSchema;

    public function test_shipped_digest_scenario_is_synced(): void
    {
        $this->artisan('bench:scenarios:sync')
            ->expectsOutputToContain('digest-article')
            ->assertSuccessful();

        $scenario = Scenario::query()->where('slug', 'digest-article')->firstOrFail();
        $this->assertSame(ResponseFormat::JsonSchema, $scenario->response_format);
        $this->assertSame(['score', 'announcement'], $scenario->json_schema['required']);
        $this->assertSame(300, $scenario->params_json['max_tokens']);
        $this->assertArrayHasKey('announcement_length_ok', $scenario->metrics_json);
    }

    public function test_resync_updates_and_marks_removed(): void
    {
        config(['scenarios' => [
            'a' => ['name' => 'A', 'system_prompt' => 'sys', 'user_template' => '{{input}}'],
            'b' => ['name' => 'B', 'system_prompt' => 'sys', 'user_template' => '{{input}}'],
        ]]);
        $this->artisan('bench:scenarios:sync')->assertSuccessful();

        config(['scenarios' => ['a' => ['name' => 'A2', 'system_prompt' => 'sys2', 'user_template' => '{{input}}']]]);
        $this->artisan('bench:scenarios:sync')
            ->expectsOutputToContain('0 added, 1 updated, 1 removed')
            ->assertSuccessful();

        $this->assertSame('A2', Scenario::query()->where('slug', 'a')->value('name'));
        $this->assertFalse(Scenario::query()->where('slug', 'b')->firstOrFail()->available);
    }

    public function test_definition_snapshot_roundtrip(): void
    {
        $this->artisan('bench:scenarios:sync')->assertSuccessful();
        $definition = Scenario::query()->where('slug', 'digest-article')->firstOrFail()->toDefinition();

        $copy = ScenarioDefinition::fromArray($definition->slug, $definition->toArray());

        $this->assertEquals($definition, $copy);
        $this->assertTrue($copy->expectsJson());
    }
}
