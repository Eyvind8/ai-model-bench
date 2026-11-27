<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Applies database/schema.sql to the in-memory test database (project has no migrations).
 */
trait LoadsSchema
{
    protected function setUpLoadsSchema(): void
    {
        DB::unprepared((string) file_get_contents(base_path('database/schema.sql')));
    }
}
