<?php

namespace AndreasElia\PostmanGenerator\Contracts;

use AndreasElia\PostmanGenerator\Authentication\AuthenticationMethod;

interface RouteReader
{
    /**
     * Process the given output structure and return the modified structure with routes added.
     *
     * @param array $output
     * @return array
     */
    public function process(array $output): array;

    /**
     * Allow setting the authentication method that should be applied to routes processing.
     *
     * @param AuthenticationMethod|null $authentication
     * @return $this
     */
    public function setAuthentication(?AuthenticationMethod $authentication);
}
