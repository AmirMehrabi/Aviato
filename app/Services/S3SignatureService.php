<?php

namespace App\Services;

use App\Exceptions\S3Exception;
use App\Models\StorageAccessKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class S3SignatureService
{
    public function authenticate(Request $request): StorageAccessKey
    {
        $authorization = (string) $request->header('Authorization');
        $query = $request->query();
        $algorithm = $request->header('X-Amz-Algorithm') ?: ($query['X-Amz-Algorithm'] ?? null);
        $credential = $request->header('X-Amz-Credential') ?: ($query['X-Amz-Credential'] ?? null);
        $signature = $request->header('X-Amz-Signature') ?: ($query['X-Amz-Signature'] ?? null);
        $signedHeaders = $request->header('X-Amz-SignedHeaders') ?: ($query['X-Amz-SignedHeaders'] ?? null);
        $date = $request->header('X-Amz-Date') ?: ($query['X-Amz-Date'] ?? null);

        if ($authorization !== '') {
            if (! preg_match('/^AWS4-HMAC-SHA256\s+(.+)$/', $authorization, $matches)) {
                throw new S3Exception('InvalidRequest', 'The authorization header is invalid.', 400);
            }

            $parts = collect(explode(',', $matches[1]))->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
                return [$key => $value];
            });
            $algorithm = 'AWS4-HMAC-SHA256';
            $credential = $parts->get('Credential');
            $signedHeaders = $parts->get('SignedHeaders');
            $signature = $parts->get('Signature');
            $date ??= $request->header('X-Amz-Date');
        }

        if ($algorithm !== 'AWS4-HMAC-SHA256' || ! $credential || ! $signedHeaders || ! $signature || ! $date) {
            throw new S3Exception('AccessDenied', 'A valid AWS Signature Version 4 credential is required.', 403);
        }

        [$accessKeyId, $dateScope, $region, $service, $terminator] = array_pad(explode('/', (string) $credential), 5, null);
        if ($terminator !== 'aws4_request' || $service !== 's3' || $region !== config('storage.aviato_region') || ! preg_match('/^\d{8}T\d{6}Z$/', $date)) {
            throw new S3Exception('AuthorizationHeaderMalformed', 'The authorization scope is invalid.', 400);
        }

        if ($dateScope !== substr($date, 0, 8) || abs(now('UTC')->timestamp - \DateTimeImmutable::createFromFormat('!Ymd\THis\Z', $date, new \DateTimeZone('UTC'))->getTimestamp()) > 900) {
            throw new S3Exception('RequestTimeTooSkewed', 'The request timestamp is outside the allowed window.', 403);
        }

        $key = StorageAccessKey::query()->where('access_key_id', $accessKeyId)->where('status', 'active')->first();
        if (! $key) {
            throw new S3Exception('InvalidAccessKeyId', 'The access key does not exist.', 403);
        }

        $signedHeaderNames = array_filter(explode(';', strtolower((string) $signedHeaders)));
        $canonicalHeaders = $this->canonicalHeaders($request, $signedHeaderNames);
        $payloadHash = $request->header('X-Amz-Content-Sha256', 'UNSIGNED-PAYLOAD');
        $canonicalRequest = implode("\n", [
            $request->method(),
            $this->canonicalUri($request),
            $this->canonicalQuery($request),
            $canonicalHeaders['value'],
            $signedHeaders,
            $payloadHash,
        ]);
        $scope = implode('/', [$dateScope, $region, $service, 'aws4_request']);
        $stringToSign = implode("\n", [$algorithm, $date, $scope, hash('sha256', $canonicalRequest)]);
        $dateKey = hash_hmac('sha256', $dateScope, 'AWS4'.$key->secret, true);
        $regionKey = hash_hmac('sha256', $region, $dateKey, true);
        $serviceKey = hash_hmac('sha256', $service, $regionKey, true);
        $signatureKey = hash_hmac('sha256', 'aws4_request', $serviceKey, true);
        $expected = hash_hmac('sha256', $stringToSign, $signatureKey);

        if (! hash_equals($expected, strtolower((string) $signature))) {
            throw new S3Exception('SignatureDoesNotMatch', 'The request signature does not match.', 403);
        }

        $key->forceFill(['last_used_at' => now()])->save();

        return $key;
    }

    private function canonicalHeaders(Request $request, array $names): array
    {
        $lines = [];
        foreach ($names as $name) {
            $value = $request->headers->get($name);
            if ($value === null) {
                throw new S3Exception('SignedHeaderMissing', 'A signed header is missing.', 400);
            }
            $lines[] = strtolower($name).':'.preg_replace('/\s+/', ' ', trim($value));
        }
        sort($lines);
        return ['value' => implode("\n", $lines)."\n"];
    }

    private function canonicalUri(Request $request): string
    {
        return collect(explode('/', '/'.ltrim($request->path(), '/')))
            ->map(fn (string $part): string => rawurlencode(rawurldecode($part)))
            ->implode('/');
    }

    private function canonicalQuery(Request $request): string
    {
        $pairs = [];
        foreach ($request->query() as $key => $values) {
            foreach ((array) $values as $value) {
                $pairs[] = [rawurlencode((string) $key), rawurlencode((string) $value)];
            }
        }
        usort($pairs, fn (array $a, array $b): int => ($a[0].'='.$a[1]) <=> ($b[0].'='.$b[1]));
        return implode('&', array_map(fn (array $pair): string => $pair[0].'='.$pair[1], $pairs));
    }
}
