<?php

namespace AndreasElia\PostmanGenerator\Postman;

use AndreasElia\PostmanGenerator\Concerns\HasAuthentication;
use AndreasElia\PostmanGenerator\Contracts\RouteReader;
use AndreasElia\PostmanGenerator\Contracts\Generator;
use Illuminate\Contracts\Config\Repository;

class Exporter implements Generator
{
    use HasAuthentication;

    protected string $filename;

    protected array $output;

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
        return json_encode($this->output);
    }

    public function export(): void
    {
        $this->resolveAuth();

        $this->output = $this->generateStructure();
    }

    protected function generateStructure(): array
    {
        $this->output = [
            'variable' => [
                [
                    'key' => 'base_url',
                    'value' => $this->config['base_url'],
                ],
            ],
            'info' => [
                'name' => $this->filename,
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'item' => [],
            'event' => [],
        ];

        $preRequestPath = $this->config['prerequest_script'];
        $testPath = $this->config['test_script'];

        if ($preRequestPath || $testPath) {
            $scripts = [
                'prerequest' => $preRequestPath,
                'test' => $testPath,
            ];

            foreach ($scripts as $type => $path) {
                if (file_exists($path)) {
                    $this->output['event'][] = [
                        'listen' => $type,
                        'script' => [
                            'type' => 'text/javascript',
                            'exec' => file_get_contents($path),
                        ],
                    ];
                }
            }
        }

        if ($this->authentication) {
            $this->output['variable'][] = [
                'key' => 'token',
                'value' => $this->authentication->getToken(),
            ];
        }

        // Resolve the route reader via the container so implementations can be swapped
        /** @var RouteReader $reader */
        $reader = app(RouteReader::class);

        // pass authentication through if available
        if (method_exists($reader, 'setAuthentication')) {
            $reader->setAuthentication($this->authentication);
        }

        return $reader->process($this->output);
    }

    // Implement Generator contract
    public function generate(array $structure): string
    {
        return json_encode($structure);
    }
}
