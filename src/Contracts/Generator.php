<?php

namespace AndreasElia\PostmanGenerator\Contracts;

interface Generator
{
    /**
     * Generate a string representation from the provided structure.
     *
     * @param array $structure
     * @return string
     */
    public function generate(array $structure): string;
}

