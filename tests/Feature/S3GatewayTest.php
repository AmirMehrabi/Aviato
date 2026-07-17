<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\StorageAccessKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class S3GatewayTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = 'https://s3.aviato.ir';

    protected function setUp(): void
    {
        parent::setUp();
        config(['storage.object_root' => storage_path('framework/testing/s3-data')]);
        File::deleteDirectory(config('storage.object_root'));
    }

    public function test_s3_endpoint_rejects_requests_without_sigv4(): void
    {
        $this->get($this->endpoint.'/photos')
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/xml');
    }

    public function test_s3_client_can_put_head_get_list_and_delete_an_object(): void
    {
        [$bucket, $key] = $this->provisionBucket();

        $put = $this->signed('PUT', '/'.$bucket.'/hello.txt', 'hello aviato');
        $put->assertOk()->assertHeader('ETag', '"'.md5('hello aviato').'"');

        $this->signed('HEAD', '/'.$bucket.'/hello.txt')->assertOk()->assertHeader('Content-Length', '12');
        $this->signed('GET', '/'.$bucket.'/hello.txt')->assertOk();
        $this->signed('GET', '/'.$bucket)->assertOk()->assertSee('hello.txt');
        $this->signed('DELETE', '/'.$bucket.'/hello.txt')->assertNoContent();

        $this->assertDatabaseMissing('storage_objects', ['object_key' => 'hello.txt']);
    }

    public function test_s3_supports_range_reads(): void
    {
        [$bucket] = $this->provisionBucket();
        $this->signed('PUT', '/'.$bucket.'/range.txt', '0123456789')->assertOk();

        $this->signed('GET', '/'.$bucket.'/range.txt', '', null, ['Range' => 'bytes=3-6'])
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 3-6/10');
    }

    public function test_s3_supports_sigv4_presigned_get_urls(): void
    {
        [$bucket, $key] = $this->provisionBucket();
        $this->signed('PUT', '/'.$bucket.'/presigned.txt', 'download me')->assertOk();
        $accessKey = StorageAccessKey::query()->latest('id')->firstOrFail();
        $date = now('UTC')->format('Ymd\\THis\\Z');
        $scope = substr($date, 0, 8).'/aviato-1/s3/aws4_request';
        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $accessKey->access_key_id.'/'.$scope,
            'X-Amz-Date' => $date,
            'X-Amz-Expires' => '300',
            'X-Amz-SignedHeaders' => 'host',
        ];
        $canonicalQuery = collect($query)->map(fn ($value, $name) => rawurlencode($name).'='.rawurlencode($value))->sort()->implode('&');
        $canonicalRequest = "GET\n/{$bucket}/presigned.txt\n{$canonicalQuery}\nhost:s3.aviato.ir\n\nhost\nUNSIGNED-PAYLOAD";
        $stringToSign = "AWS4-HMAC-SHA256\n{$date}\n{$scope}\n".hash('sha256', $canonicalRequest);
        $dateKey = hash_hmac('sha256', substr($date, 0, 8), 'AWS4'.$accessKey->secret, true);
        $regionKey = hash_hmac('sha256', 'aviato-1', $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
        $query['X-Amz-Signature'] = hash_hmac('sha256', $stringToSign, hash_hmac('sha256', 'aws4_request', $serviceKey, true));
        $url = $this->endpoint.'/'.$bucket.'/presigned.txt?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        $this->call('GET', $url, [], [], [], ['HTTP_HOST' => 's3.aviato.ir'])->assertOk();
    }

    public function test_s3_supports_a_multipart_upload_lifecycle(): void
    {
        [$bucket] = $this->provisionBucket();
        $initiate = $this->signed('POST', '/'.$bucket.'/large.bin?uploads');
        preg_match('/<UploadId>([^<]+)<\\/UploadId>/', $initiate->getContent(), $matches);
        $uploadId = $matches[1] ?? null;
        $this->assertNotNull($uploadId);

        $this->signed('PUT', '/'.$bucket.'/large.bin?partNumber=1&uploadId='.urlencode($uploadId), 'part-one')->assertOk();
        $complete = $this->signed('POST', '/'.$bucket.'/large.bin?uploadId='.urlencode($uploadId), '<CompleteMultipartUpload><Part><PartNumber>1</PartNumber></Part></CompleteMultipartUpload>');
        $complete->assertOk()->assertSee('large.bin');
        $this->assertDatabaseHas('storage_objects', ['object_key' => 'large.bin', 'size_bytes' => 8]);
    }

    public function test_s3_key_cannot_access_another_project_bucket(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        $bucket = $other->ensureDefaultProject()->storageBuckets()->create(['name' => 'private-other-bucket']);
        $key = $customer->ensureDefaultProject()->storageAccessKeys()->create(['access_key_id' => 'AVTOTHERPROJECTKEY', 'secret' => 'secret-value']);

        $this->signed('HEAD', '/'.$bucket->name, $key)->assertForbidden();
    }

    private function provisionBucket(): array
    {
        $customer = Customer::factory()->create();
        $project = $customer->ensureDefaultProject();
        $bucket = $project->storageBuckets()->create(['name' => 'test-storage-bucket']);
        $key = $project->storageAccessKeys()->create(['access_key_id' => 'AVTTESTSTORAGEKEY', 'secret' => 'secret-value']);

        return [$bucket->name, $key];
    }

    private function signed(string $method, string $path, string|StorageAccessKey $bodyOrKey = '', ?StorageAccessKey $maybeKey = null, array $extraHeaders = [])
    {
        $body = is_string($bodyOrKey) ? $bodyOrKey : '';
        $key = is_string($bodyOrKey) ? $maybeKey : $bodyOrKey;
        if (! $key) {
            $key = StorageAccessKey::query()->latest('id')->firstOrFail();
        }
        $host = 's3.aviato.ir';
        $date = now('UTC')->format('Ymd\\THis\\Z');
        $shortDate = substr($date, 0, 8);
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        $parsed = parse_url($this->endpoint.$path);
        $requestPath = $parsed['path'] ?? '/';
        parse_str($parsed['query'] ?? '', $query);
        $canonicalQuery = collect($query)->flatMap(fn ($values, $name) => collect((array) $values)->map(fn ($value) => rawurlencode((string) $name).'='.rawurlencode((string) $value)))->sort()->implode('&');
        $canonicalHeaders = "host:{$host}\n".'x-amz-content-sha256:UNSIGNED-PAYLOAD' . "\n" . "x-amz-date:{$date}\n";
        $canonicalRequest = implode("\n", [$method, $requestPath, $canonicalQuery, $canonicalHeaders, $signedHeaders, 'UNSIGNED-PAYLOAD']);
        $scope = $shortDate.'/aviato-1/s3/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n{$date}\n{$scope}\n".hash('sha256', $canonicalRequest);
        $dateKey = hash_hmac('sha256', $shortDate, 'AWS4'.$key->secret, true);
        $regionKey = hash_hmac('sha256', 'aviato-1', $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $serviceKey, true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        $headers = [
            'Host' => $host,
            'X-Amz-Date' => $date,
            'X-Amz-Content-Sha256' => 'UNSIGNED-PAYLOAD',
            'Authorization' => 'AWS4-HMAC-SHA256 Credential='.$key->access_key_id.'/'.$scope.',SignedHeaders='.$signedHeaders.',Signature='.$signature,
        ];

        $server = [
            'HTTP_HOST' => $host,
            'HTTP_X_AMZ_DATE' => $date,
            'HTTP_X_AMZ_CONTENT_SHA256' => 'UNSIGNED-PAYLOAD',
            'HTTP_AUTHORIZATION' => $headers['Authorization'],
        ];
        foreach ($extraHeaders as $name => $value) $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;

        return $this->call($method, $this->endpoint.$path, [], [], [], $server, $body);
    }
}
