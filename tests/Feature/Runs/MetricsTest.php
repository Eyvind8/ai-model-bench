<?php

namespace Tests\Feature\Runs;

use App\Metrics\LanguageMatchMetric;
use App\Metrics\MaxLengthMetric;
use App\Metrics\MetricContext;
use App\Metrics\ScoreErrorMetric;
use App\Models\DatasetItem;
use Tests\TestCase;

class MetricsTest extends TestCase
{
    private function item(?int $expected = null, ?string $lang = null, string $input = ''): DatasetItem
    {
        return new DatasetItem(['expected_score' => $expected, 'meta_json' => $lang ? ['lang' => $lang] : null, 'input' => $input]);
    }

    public function test_max_length(): void
    {
        $metric = new MaxLengthMetric;

        $ok = $metric->compute(new MetricContext(null, ['a' => str_repeat('я', 200)], null), ['field' => 'a', 'max' => 200]);
        $long = $metric->compute(new MetricContext(null, ['a' => str_repeat('я', 201)], null), ['field' => 'a', 'max' => 200]);

        $this->assertSame(1.0, $ok?->value);
        $this->assertSame(0.0, $long?->value);
        $this->assertSame('201 chars', $long->details);
        $this->assertNull($metric->compute(new MetricContext(null, null, null), ['field' => 'a', 'max' => 1]));
    }

    public function test_score_error(): void
    {
        $metric = new ScoreErrorMetric;

        $this->assertSame(2.0, $metric->compute(new MetricContext(null, ['score' => 7], $this->item(9)), [])?->value);
        $this->assertNull($metric->compute(new MetricContext(null, ['score' => 7], $this->item()), []));
        $this->assertNull($metric->compute(new MetricContext(null, ['score' => 'high'], $this->item(9)), []));
    }

    public function test_language_match(): void
    {
        $metric = $this->app->make(LanguageMatchMetric::class);
        $uk = 'НБУ знизив облікову ставку до тринадцяти відсотків, це їхнє рішення';

        $match = $metric->compute(new MetricContext(null, ['a' => $uk], $this->item(lang: 'uk')), ['field' => 'a']);
        $mismatch = $metric->compute(new MetricContext(null, ['a' => 'The national bank cut its key rate today'], $this->item(lang: 'uk')), ['field' => 'a']);
        $detected = $metric->compute(new MetricContext(null, ['a' => $uk], $this->item(input: $uk)), ['field' => 'a']);

        $this->assertSame(1.0, $match?->value);
        $this->assertSame(0.0, $mismatch?->value);
        $this->assertSame('article uk, answer en', $mismatch->details);
        $this->assertSame(1.0, $detected?->value);
        $this->assertNull($metric->compute(new MetricContext(null, ['a' => $uk], null), ['field' => 'a']));
    }
}
