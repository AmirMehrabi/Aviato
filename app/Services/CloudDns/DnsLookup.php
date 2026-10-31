<?php

namespace App\Services\CloudDns;

use RuntimeException;
use Symfony\Component\Process\Process;

class DnsLookup
{
    public function txt(string $name): array
    {
        $records = @dns_get_record($name, DNS_TXT);

        return array_column($records ?: [], 'txt');
    }

    public function nameservers(string $name): array
    {
        return array_values(array_map(fn ($r) => strtolower(rtrim($r['target'], '.')), @dns_get_record($name, DNS_NS) ?: []));
    }

    /** Check the parent's delegation, rather than trusting the zone's own apex NS records. */
    public function delegation(string $name): array
    {
        $labels = explode('.', $name);
        array_shift($labels);
        while ($labels !== []) {
            $parents = $this->nameservers(implode('.', $labels));
            if ($parents !== []) {
                foreach (array_slice($parents, 0, 3) as $parent) {
                    $process = new Process(['dig', '@'.$parent, $name.'.', 'NS', '+norecurse', '+noall', '+answer', '+authority', '+time=2', '+tries=1']);
                    $process->setTimeout(6)->run();
                    if ($process->isSuccessful() && preg_match_all('/^'.preg_quote($name, '/').'\.\s+\d+\s+IN\s+NS\s+(\S+)/mi', $process->getOutput(), $matches)) {
                        $result = array_map(fn ($ns) => strtolower(rtrim($ns, '.')), $matches[1]);
                        sort($result);

                        return array_values(array_unique($result));
                    }
                }
                throw new RuntimeException('Could not check parent delegation; retry later.');
            }
            array_shift($labels);
        }
        throw new RuntimeException('Could not locate parent nameservers.');
    }
}
