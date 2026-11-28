<?php

namespace App\Contracts;

interface HostResolverInterface
{
    /**
     * @return list<string> IP addresses of the host, empty when it does not resolve
     */
    public function resolve(string $host): array;
}
