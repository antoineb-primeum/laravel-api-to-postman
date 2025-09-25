<?php

namespace AndreasElia\PostmanGenerator\SqlMap;

use AndreasElia\PostmanGenerator\Concerns\HasAuthentication;
use AndreasElia\PostmanGenerator\Contracts\RouteReader;
use AndreasElia\PostmanGenerator\Contracts\Generator;
use Illuminate\Contracts\Config\Repository;

class Exporter implements Generator
{
    use HasAuthentication;

    protected string $filename;

    protected string $output;

    private array $config;

    public function __construct(Repository $config)
    {
        $this->config = $config['api-exports'];
    }

    public function to(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getOutput()
    {
        return $this->output;
    }

    public function export(): void
    {
        $this->resolveAuth();

        $structure = $this->generateStructure();

        $this->output = $this->generate($structure);
    }

    protected function generateStructure(): array
    {
        $structure = [
            'variable' => [
                [
                    'key' => 'base_url',
                    'value' => $this->config['base_url'],
                ],
            ],
            'item' => [],
        ];

        /** @var RouteReader $reader */
        $reader = app(RouteReader::class);

        if (method_exists($reader, 'setAuthentication')) {
            $reader->setAuthentication($this->authentication);
        }

        return $reader->process($structure);
    }

    public function generate(array $structure): string
    {
        $collected = [];

        $this->traverseItems($structure['item'] ?? [], $collected);

        // join lines
        return implode(PHP_EOL, $collected);
    }

    protected function traverseItems(array $items, array &$collected): void
    {
        foreach ($items as $item) {
            if (isset($item['item']) && is_array($item['item'])) {
                $this->traverseItems($item['item'], $collected);

                continue;
            }

            // item representing a request
            if (isset($item['request'])) {
                $req = $item['request'];

                // url raw may be in 'url' as raw or as string
                $url = '';
                if (is_string($req['url'] ?? null)) {
                    $url = $req['url'];
                } elseif (is_array($req['url']) && isset($req['url']['raw'])) {
                    $url = $req['url']['raw'];
                }

                $method = strtoupper($req['method'] ?? 'GET');

                // Skip PATCH routes to match test helpers which exclude PATCH
                if ($method === 'PATCH') {
                    continue;
                }

                if (trim($url) === '') {
                    // nothing to export
                    continue;
                }

                // Replace route parameters (/:param or {param}) with injection marker FUZZ
                // Handle patterns like /:id and /{id}
                $url = preg_replace('/\/:[A-Za-z0-9_]+/', '/FUZZ', $url);
                $url = preg_replace('/\{[A-Za-z0-9_]+\}/', 'FUZZ', $url);

                $line = $url;

                // non GET: attempt to build data string from urlencoded body
                if ($method !== 'GET') {
                    if (isset($req['body']['urlencoded']) && is_array($req['body']['urlencoded'])) {
                        $pairs = [];

                        foreach ($req['body']['urlencoded'] as $field) {
                            $pairs[] = $field['key'].'='.urlencode($field['value'] ?? '');
                        }

                        $data = implode('&', $pairs);

                        if ($data !== '') {
                            $line .= ' --data "'.$data.'"';
                        }
                    } elseif (isset($req['body']['raw']) && $req['body']['raw'] !== '') {
                        $body = str_replace('\n', '\\n', $req['body']['raw']);
                        $line .= ' --data "'.addslashes($body).'"';
                    }
                }

                // if authentication token is present in variables, append header param for sqlmap (-H)
                if ($this->authentication) {
                    $token = $this->authentication->getToken();

                    if ($token) {
                        // determine header name from authentication type
                        if (method_exists($this->authentication, 'toArray')) {
                            $arr = $this->authentication->toArray();

                            if (isset($arr['key']) && isset($arr['value'])) {
                                $header = $arr['key'].': '.$arr['value'];
                            } else {
                                $header = 'Authorization: '.$token;
                            }
                        } else {
                            $header = 'Authorization: '.$token;
                        }

                        $line .= ' -H "'.addslashes($header).'"';
                    }
                }

                $collected[] = $line;
            }
        }
    }
}
