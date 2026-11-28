<?php

namespace Tests\Feature\Datasets;

use App\Enums\FetchStatus;
use App\Models\Dataset;
use App\Models\DatasetItem;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\LoadsSchema;
use Tests\TestCase;

class DatasetImportTest extends TestCase
{
    use LoadsSchema;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns();
        $this->dir = sys_get_temp_dir().'/bench-'.uniqid();
        mkdir($this->dir);
        Http::fake([
            'news.test/nbu' => Http::response($this->fixture('articles/article.html'), 200, ['Content-Type' => 'text/html']),
            'news.test/paywall' => Http::response($this->fixture('articles/short.html'), 200, ['Content-Type' => 'text/html']),
        ]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);

        parent::tearDown();
    }

    private function file(string $name, string $content): string
    {
        file_put_contents($this->dir.'/'.$name, $content);

        return $this->dir.'/'.$name;
    }

    private function item(string $url): DatasetItem
    {
        return DatasetItem::query()->where('source_url', $url)->firstOrFail();
    }

    public function test_csv_with_text_and_url_rows(): void
    {
        $csv = "\xEF\xBB\xBFtitle,text,url,score,author\n"
            ."Local,Some article text,,7,Ann\n"
            .",,https://news.test/nbu,9,\n"
            .",,https://news.test/paywall,,\n"
            .",,,,\n"
            ."Bad,text,,11,\n";

        $this->artisan('bench:dataset:import', ['file' => $this->file('a.csv', $csv), '--name' => 'News'])
            ->expectsOutputToContain('2 added, 2 urls queued, 1 skipped')
            ->expectsOutputToContain('row 4: no input text and no url')
            ->expectsOutputToContain('row 5: expected_score must be 1-10')
            ->assertSuccessful();

        $local = DatasetItem::query()->where('title', 'Local')->firstOrFail();
        $this->assertSame('Some article text', $local->input);
        $this->assertSame(7, $local->expected_score);
        $this->assertSame(['author' => 'Ann'], $local->meta_json);
        $this->assertNull($local->fetch_status);

        $nbu = $this->item('https://news.test/nbu');
        $this->assertSame(FetchStatus::Ok, $nbu->fetch_status);
        $this->assertSame(9, $nbu->expected_score);
        $this->assertStringContainsString('НБУ знизив', (string) $nbu->title);
        $this->assertStringContainsString('облікову ставку', $nbu->input);
        $this->assertSame('uk', $nbu->meta_json['lang']);
        $this->assertSame('news.test', $nbu->meta_json['site']);
        $this->assertNotNull($nbu->source_html);

        $paywall = $this->item('https://news.test/paywall');
        $this->assertSame(FetchStatus::Failed, $paywall->fetch_status);
        $this->assertStringContainsString('Text too short', (string) $paywall->fetch_error);
        $this->assertSame(3, DatasetItem::query()->ready()->count());
    }

    public function test_jsonl_and_json_append_to_existing_dataset(): void
    {
        $dataset = Dataset::query()->create(['name' => 'D']);
        $jsonl = $this->file('a.jsonl', "{\"input\":\"one\",\"expected_score\":3}\n\n{\"content\":\"two\"}\n");
        $json = $this->file('b.json', json_encode(['items' => [['text' => 'three', 'announcement' => 'A']]]));

        $this->artisan('bench:dataset:import', ['file' => $jsonl, '--dataset' => $dataset->id])->assertSuccessful();
        $this->artisan('bench:dataset:import', ['file' => $json, '--dataset' => $dataset->id])->assertSuccessful();

        $this->assertSame(['one', 'two', 'three'], $dataset->items()->orderBy('id')->pluck('input')->all());
        $this->assertSame('A', $dataset->items()->where('input', 'three')->value('expected_announcement'));
    }

    public function test_import_requires_exactly_one_target(): void
    {
        $file = $this->file('a.jsonl', '{"input":"x"}');

        $this->artisan('bench:dataset:import', ['file' => $file])->assertFailed();
        $this->artisan('bench:dataset:import', ['file' => $file, '--name' => 'a', '--dataset' => 1])->assertFailed();
    }

    public function test_invalid_file(): void
    {
        $this->artisan('bench:dataset:import', ['file' => $this->file('a.json', '{bad'), '--name' => 'x'])
            ->expectsOutputToContain('Invalid JSON')
            ->assertFailed();
        $this->artisan('bench:dataset:import', ['file' => $this->file('a.txt', 'x'), '--name' => 'x'])->assertFailed();
    }

    public function test_fetch_urls_skips_duplicates_and_invalid(): void
    {
        $this->artisan('bench:dataset:fetch-urls', [
            'urls' => ['https://news.test/nbu', 'https://news.test/nbu', 'not a url'],
            '--name' => 'U',
        ])
            ->expectsOutputToContain('Invalid URL')
            ->expectsOutputToContain('1 url(s) queued')
            ->assertSuccessful();

        $this->artisan('bench:dataset:fetch-urls', ['urls' => ['https://news.test/nbu'], '--dataset' => 1])
            ->expectsOutputToContain('duplicate')
            ->assertSuccessful();
    }

    public function test_refetch_reextracts_stored_html_without_download(): void
    {
        $this->artisan('bench:dataset:fetch-urls', ['urls' => ['https://news.test/nbu'], '--name' => 'U'])->assertSuccessful();
        $item = $this->item('https://news.test/nbu');
        $item->update(['input' => '', 'fetch_status' => FetchStatus::Failed]);

        $this->artisan('bench:dataset:refetch', ['dataset' => $item->dataset_id, '--failed' => true])
            ->expectsOutputToContain('1 item(s) queued')
            ->assertSuccessful();

        $this->assertSame(FetchStatus::Ok, $item->fresh()->fetch_status);
        Http::assertSentCount(1);
    }

    public function test_list_shows_status(): void
    {
        $this->artisan('bench:dataset:fetch-urls', ['urls' => ['https://news.test/paywall'], '--name' => 'U'])->assertSuccessful();

        $this->artisan('bench:dataset:list')->expectsOutputToContain('U')->assertSuccessful();
        $this->artisan('bench:dataset:list', ['dataset' => 1])->expectsOutputToContain('failed')->assertSuccessful();
        $this->artisan('bench:dataset:list', ['dataset' => 99])->assertFailed();
    }
}
