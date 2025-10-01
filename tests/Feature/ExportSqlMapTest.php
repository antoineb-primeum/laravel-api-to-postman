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
        config()->set('api-exports.disk', 'local');
        $dir = storage_path('app/private/sqlmap/');
        if (is_dir($dir)) {
            foreach (glob($dir.'*.template') as $file) {
                unlink($file);
            }
        }
    }

    public function test_standard_export_works()
    {
        $timestamp = date('Y_m_d_His');
        $this->artisan('export:sqlmap')->assertExitCode(0);
        $dir = storage_path('app/private/sqlmap/');
        $files = glob($dir.$timestamp.'*.template');
        $this->assertNotEmpty($files, 'Aucun template SQLMap généré');

        $postman = $this->app->make(\AndreasElia\PostmanGenerator\Postman\Exporter::class);
        $postman->to('tmp')->export();
        $collection = json_decode($postman->getOutput(), true);

        // Compter les routes exportées par SQLMap (exclure PATCH)
        $expected = 0;
        $this->countSqlMapItems($collection['item'] ?? [], $expected);

        $this->assertEquals(
            $expected,
            count($files),
            sprintf('Nombre attendu de templates SQLMap : %d, nombre généré : %d', $expected, count($files))
        );
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertStringContainsString('HTTP/1.1', $content);
            $this->assertMatchesRegularExpression('/(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD) .+ HTTP\/1\.1/', $content);
            $this->assertStringContainsString('Host:', $content);
            $this->assertStringContainsString('FUZZ', $content);
        }
    }

    private function countSqlMapItems(array $items, &$count)
    {
        foreach ($items as $item) {
            if (isset($item['item']) && is_array($item['item'])) {
                $this->countSqlMapItems($item['item'], $count);
                continue;
            }
            if (isset($item['request'])) {
                $method = strtoupper($item['request']['method'] ?? 'GET');
                if ($method === 'PATCH') {
                    continue;
                }
                $count++;
            }
        }
    }

    public function test_bearer_export_works()
    {
        $this->artisan('export:sqlmap --bearer=1234567890')->assertExitCode(0);
        $dir = storage_path('app/private/sqlmap/');
        $files = glob($dir.'*.template');
        $this->assertNotEmpty($files, 'Aucun template SQLMap généré');
        $found = false;
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, 'Authorization:')) {
                $this->assertStringContainsString('1234567890', $content);
                $found = true;
            }
        }
        $this->assertTrue($found, 'Aucun header Authorization avec le token trouvé dans les templates SQLMap');
    }

    public function test_structured_export_works()
    {
        config(['api-exports.structured' => true]);
        $this->artisan('export:sqlmap')->assertExitCode(0);
        $dir = storage_path('app/private/sqlmap/');
        $files = glob($dir.'*.template');
        $this->assertGreaterThan(0, count($files));
    }
}
