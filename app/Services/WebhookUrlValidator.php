<?php

namespace App\Services;

class WebhookUrlValidator
{
    public function isSafeHttpUrl(?string $url): bool
    {
        if ($url === null || $url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.local')) {
            return false;
        }

        return $this->hostResolvesOnlyToPublicIps($host);
    }

    private function hostResolvesOnlyToPublicIps(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->isPublicIp($host);
        }

        $records = dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === false || $records === []) {
            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (! is_string($ip) || ! $this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
