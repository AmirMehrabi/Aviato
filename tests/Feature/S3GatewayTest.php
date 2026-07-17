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

    private function signed(string $method, string $path, string|StorageAccessKey $bodyOrKey = '', ?StorageAccessKey $maybeKey = null)
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
        $canonicalHeaders = "host:{$host}\n".'x-amz-content-sha256:UNSIGNED-PAYLOAD' . "\n" . "x-amz-date:{$date}\n";
        $canonicalRequest = implode("\n", [$method, $path, '', $canonicalHeaders, $signedHeaders, 'UNSIGNED-PAYLOAD']);
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

        return $this->call($method, $this->endpoint.$path, [], [], [], $server, $body);
    }
}
