<?php

namespace Tests\Concerns;

use App\Models\Dataset;
use App\Models\LlmModel;
use App\Models\Scenario;

/**
 * Test providers + synced models/scenarios + a small labeled dataset.
 */
trait SeedsBench
{
    use LoadsSchema;

    protected Dataset $dataset;

    protected function seedBench(): void
    {
        $this->useTestProviders(['groq' => ['limits' => ['rpm' => 100, 'min_interval_ms' => 0]]]);
        $this->artisan('bench:models:sync')->assertSuccessful();
        $this->artisan('bench:scenarios:sync')->assertSuccessful();

        $this->dataset = Dataset::query()->create(['name' => 'News']);
        $this->dataset->items()->create(['title' => 'Rate cut', 'input' => 'НБУ знизив облікову ставку до 13 відсотків', 'expected_score' => 9, 'meta_json' => ['lang' => 'uk']]);
        $this->dataset->items()->create(['title' => 'Weather', 'input' => 'Завтра буде хмарно', 'meta_json' => ['lang' => 'uk']]);
    }

    protected function qwen(): LlmModel
    {
        return LlmModel::query()->where('model_id', 'qwen/qwen3-32b')->firstOrFail();
    }

    protected function digest(): Scenario
    {
        return Scenario::query()->where('slug', 'digest-article')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    protected function chatReply(int $score = 7, string $announcement = 'НБУ знизив ставку: кредити подешевшають.'): array
    {
        return [
            'choices' => [['message' => ['content' => json_encode(['score' => $score, 'announcement' => $announcement], JSON_UNESCAPED_UNICODE)], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 20],
        ];
    }
}
