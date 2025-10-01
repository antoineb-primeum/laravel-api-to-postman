<?php
use AndreasElia\PostmanGenerator\SqlMap\Exporter;
use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;

class SqlMapTemplateFormatTest extends TestCase
{
    public function test_template_format_for_route()
    {
        $config = new Repository([
            'api-exports' => [
                'base_url' => 'http://localhost',
                'headers' => [
                    ['key' => 'Accept', 'value' => 'application/json'],
                    ['key' => 'Content-Type', 'value' => 'application/json'],
                ],
            ],
        ]);
        $exporter = new Exporter($config);
        $structure = [
            'item' => [
                [
                    'name' => 'users/{id}',
                    'request' => [
                        'method' => 'GET',
                        'header' => [
                            ['key' => 'Accept', 'value' => 'application/json'],
                            ['key' => 'Content-Type', 'value' => 'application/json'],
                        ],
                        'url' => [
                            'raw' => 'http://localhost/users/{id}',
                        ],
                    ],
                ],
                [
                    'name' => 'posts',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Accept', 'value' => 'application/json'],
                            ['key' => 'Content-Type', 'value' => 'application/json'],
                        ],
                        'url' => [
                            'raw' => 'http://localhost/posts',
                        ],
                        'body' => [
                            'urlencoded' => [
                                ['key' => 'title', 'value' => ''],
                                ['key' => 'content', 'value' => ''],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $items = [];
        $exporter->traverseItems($structure['item'], $items);
        $host = 'localhost';
        $timestamp = '2025_10_01_125211';
        foreach ($items as $item) {
            $req = $item['request'];
            $method = strtoupper($req['method'] ?? 'GET');
            $url = $req['url']['raw'] ?? $req['url'] ?? '/';
            $url = preg_replace('/\{[A-Za-z0-9_]+\}/', 'FUZZ', $url);
            $url = preg_replace('/\:[A-Za-z0-9_]+/', 'FUZZ', $url);
            $template = $method.' '.$url.' HTTP/1.1' . "\n";
            $template .= 'Host: '.$host."\n";
            foreach ($req['header'] ?? [] as $header) {
                if (strtolower($header['key']) !== 'host') {
                    $template .= $header['key'].': '.$header['value']."\n";
                }
            }
            $body = '';
            if (in_array($method, ['POST','PUT','PATCH','DELETE'])) {
                if (isset($req['body']['urlencoded']) && is_array($req['body']['urlencoded'])) {
                    $pairs = [];
                    foreach ($req['body']['urlencoded'] as $field) {
                        $pairs[] = $field['key'].'=FUZZ';
                    }
                    $body = implode('&', $pairs);
                } elseif (isset($req['body']['raw']) && $req['body']['raw'] !== '') {
                    $body = str_replace(['\n','\r'], '', $req['body']['raw']);
                }
                if ($body !== '') {
                    $template .= 'Content-Length: '.strlen($body)."\n\n";
                    $template .= $body;
                }
            }
            $this->assertStringContainsString('HTTP/1.1', $template);
            $this->assertMatchesRegularExpression('/(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD) .+ HTTP\/1\.1/', $template);
            $this->assertStringContainsString('Host: '.$host, $template);
            $this->assertStringContainsString('FUZZ', $template);
            if ($method === 'POST') {
                $this->assertStringContainsString('Content-Length:', $template);
                $this->assertStringContainsString('title=FUZZ', $template);
                $this->assertStringContainsString('content=FUZZ', $template);
            }
        }
    }
}
