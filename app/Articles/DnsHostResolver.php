<?php

namespace App\Articles;

use App\Contracts\HostResolverInterface;

final class DnsHostResolver implements HostResolverInterface
{
    public function resolve(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];

        $records = @dns_get_record($host, DNS_AAAA) ?: [];
        foreach ($records as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
