<?php

namespace App\Articles;

use App\Contracts\HostResolverInterface;

/**
 * SSRF protection: only public http(s) hosts may be fetched.
 */
final class UrlGuard
{
    public function __construct(private readonly HostResolverInterface $resolver) {}

    /**
     * @throws ArticleFetchException
     */
    public function assertAllowed(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = trim((string) ($parts['host'] ?? ''), '[]');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new ArticleFetchException("Only http(s) URLs are allowed: {$url}");
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolver->resolve($host);
        if ($ips === []) {
            throw new ArticleFetchException("Host does not resolve: {$host}");
        }

        foreach ($ips as $ip) {
            if (! $this->isPublic($ip)) {
                throw new ArticleFetchException("Private or reserved address is not allowed: {$host}");
            }
        }
    }

    private function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
