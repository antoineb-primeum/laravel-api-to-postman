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
        return $this->output;
    }

    public function export(): void
    {
        $this->resolveAuth();
        $this->output = $this->generateStructure();
    }

    public function generateStructure(): array
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

    public function traverseItems(array $items, array &$collected): void
    {
        foreach ($items as $item) {
            if (isset($item['item']) && is_array($item['item'])) {
                $this->traverseItems($item['item'], $collected);
                continue;
            }
            if (isset($item['request'])) {
                $collected[] = $item;
            }
        }
    }
}
