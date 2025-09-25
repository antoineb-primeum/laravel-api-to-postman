<?php

namespace AndreasElia\PostmanGenerator\Tests\Feature;

use AndreasElia\PostmanGenerator\Tests\Fixtures\CollectionHelpersTrait;
use AndreasElia\PostmanGenerator\Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ExportSqlMapTest extends TestCase
{
    use CollectionHelpersTrait;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('api-exports.filename', 'test.sqlmap');

        Storage::disk()->deleteDirectory('sqlmap');
    }

    public function test_standard_export_works()
    {
        $this->artisan('export:sqlmap')->assertExitCode(0);

        $content = Storage::get('sqlmap/'.config('api-exports.filename'));

        $lines = array_filter(explode(PHP_EOL, trim($content)));

        // Generate the Postman collection in memory to determine expected number of routes
        $postman = $this->app->make(\AndreasElia\PostmanGenerator\Postman\Exporter::class);
        $postman->to('tmp')->export();
        $collection = json_decode($postman->getOutput(), true);

        $expected = $this->countCollectionItems($collection['item'] ?? []);

        // We compare the number of lines to the number of processed routes
        $this->assertEquals($expected, count($lines));
    }

    public function test_bearer_export_works()
    {
        $this->artisan('export:sqlmap --bearer=1234567890')->assertExitCode(0);

        $content = Storage::get('sqlmap/'.config('api-exports.filename'));

        $this->assertStringContainsString('1234567890', $content);

        $lines = array_filter(explode(PHP_EOL, trim($content)));

        $this->assertGreaterThan(0, count($lines));
    }

    public function test_structured_export_works()
    {
        config(['api-exports.structured' => true]);

        $this->artisan('export:sqlmap')->assertExitCode(0);

        $content = Storage::get('sqlmap/'.config('api-exports.filename'));

        $lines = array_filter(explode(PHP_EOL, trim($content)));

        $this->assertGreaterThan(0, count($lines));
    }
}
