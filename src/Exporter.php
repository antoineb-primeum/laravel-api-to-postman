<?php

namespace AndreasElia\PostmanGenerator;

use AndreasElia\PostmanGenerator\Postman\Exporter as PostmanExporter;
use Illuminate\Contracts\Config\Repository;

class Exporter
{
    protected PostmanExporter $exporter;

    public function __construct(Repository $config, PostmanExporter $postmanExporter = null)
    {
        // Allow the container to inject Postman\Exporter or instantiate one lazily
        $this->exporter = $postmanExporter ?? new PostmanExporter($config);
    }

    public function to(string $filename): self
    {
        $this->exporter->to($filename);

        return $this;
    }

    public function setAuthentication($authentication): self
    {
        if (method_exists($this->exporter, 'setAuthentication')) {
            $this->exporter->setAuthentication($authentication);
        }

        return $this;
    }

    public function export(): void
    {
        $this->exporter->export();
    }

    public function getOutput()
    {
        return $this->exporter->getOutput();
    }
}
