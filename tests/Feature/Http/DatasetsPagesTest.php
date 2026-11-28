<?php

namespace Tests\Feature\Http;

use App\Enums\FetchStatus;
use App\Models\Dataset;
use App\Models\DatasetItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsBench;
use Tests\TestCase;

class DatasetsPagesTest extends TestCase
{
    use SeedsBench;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBench();
        $this->fakeDns();
    }

    private function url(string $path = ''): string
    {
        return "/datasets/{$this->dataset->id}{$path}";
    }

    public function test_index_and_create(): void
    {
        $this->get('/datasets')->assertOk()->assertSee('News');

        $response = $this->post('/datasets', ['name' => 'Tech']);

        $tech = Dataset::query()->where('name', 'Tech')->firstOrFail();
        $response->assertRedirect("/datasets/{$tech->id}");
        $this->post('/datasets', ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_show_lists_items(): void
    {
        $this->get($this->url())->assertOk()->assertSee('Rate cut')->assertSee('Weather');
    }

    public function test_import_urls(): void
    {
        Http::fake(['news.test/*' => Http::response($this->fixture('articles/article.html'), 200, ['Content-Type' => 'text/html'])]);

        $this->post($this->url('/urls'), ['urls' => "https://news.test/a\n\nhttps://news.test/a\nbad url"])
            ->assertRedirect($this->url())
            ->assertSessionHas('status');

        $item = DatasetItem::query()->where('source_url', 'https://news.test/a')->firstOrFail();
        $this->assertSame(FetchStatus::Ok, $item->fetch_status);
        $this->assertSame(1, DatasetItem::query()->where('source_url', 'https://news.test/a')->count());
    }

    public function test_import_file_upload(): void
    {
        $file = UploadedFile::fake()->createWithContent('rows.csv', "title,text,score\nA,Text A,5\nB,Text B,\n");

        $this->post($this->url('/import'), ['file' => $file])->assertRedirect($this->url());

        $this->assertSame(5, $this->dataset->items()->where('title', 'A')->value('expected_score'));
        $this->assertSame(4, $this->dataset->items()->count());
    }

    public function test_import_rejects_wrong_extension(): void
    {
        $file = UploadedFile::fake()->createWithContent('rows.exe', 'x');

        $this->post($this->url('/import'), ['file' => $file])->assertSessionHasErrors('file');
    }

    public function test_add_edit_delete_item(): void
    {
        $this->post($this->url('/items'), ['title' => 'Manual', 'input' => 'Manual text', 'expected_score' => 3])->assertRedirect($this->url());
        $item = DatasetItem::query()->where('title', 'Manual')->firstOrFail();
        $this->assertSame(3, $item->expected_score);

        $this->get($this->url("/items/{$item->id}/edit"))->assertOk()->assertSee('Manual text');

        $item->update(['fetch_status' => FetchStatus::Failed, 'fetch_error' => 'paywall', 'input' => '']);
        $this->put($this->url("/items/{$item->id}"), ['title' => 'Manual', 'input' => 'Pasted text', 'expected_score' => ''])->assertRedirect($this->url());
        $item->refresh();
        $this->assertSame('Pasted text', $item->input);
        $this->assertSame(FetchStatus::Manual, $item->fetch_status);
        $this->assertNull($item->fetch_error);
        $this->assertNull($item->expected_score);

        $this->delete($this->url("/items/{$item->id}"))->assertRedirect($this->url());
        $this->assertNull(DatasetItem::query()->find($item->id));
    }

    public function test_item_of_other_dataset_is_404(): void
    {
        $other = Dataset::query()->create(['name' => 'Other']);
        $item = $other->items()->create(['input' => 'x']);

        $this->get($this->url("/items/{$item->id}/edit"))->assertNotFound();
    }

    public function test_item_validation(): void
    {
        $this->post($this->url('/items'), ['input' => '', 'expected_score' => 11])
            ->assertSessionHasErrors(['input', 'expected_score']);
    }

    public function test_labeling(): void
    {
        [$first, $second] = $this->dataset->items()->orderBy('id')->get()->all();

        $this->get($this->url('/label'))->assertOk()->assertSee('Weather');

        $this->post($this->url("/items/{$second->id}/label"), ['expected_score' => 2, 'expected_announcement' => 'Хмарно'])
            ->assertRedirect();
        $second->refresh();
        $this->assertSame(2, $second->expected_score);
        $this->assertSame('Хмарно', $second->expected_announcement);

        $this->get($this->url("/label?item={$first->id}"))->assertOk()->assertSee('Rate cut')->assertSee('2 / 2');
        $this->post($this->url("/items/{$first->id}/label"), ['expected_score' => 0])->assertSessionHasErrors('expected_score');
    }

    public function test_refetch_and_delete_dataset(): void
    {
        $this->post($this->url('/refetch'))->assertRedirect($this->url())->assertSessionHas('status');

        $this->delete($this->url())->assertRedirect('/datasets');
        $this->assertNull(Dataset::query()->find($this->dataset->id));
        $this->assertSame(0, DatasetItem::query()->count());
    }
}
