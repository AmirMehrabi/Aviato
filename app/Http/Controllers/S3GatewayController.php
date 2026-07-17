<?php

namespace App\Http\Controllers;

use App\Exceptions\S3Exception;
use App\Models\StorageAccessKey;
use App\Models\StorageBucket;
use App\Models\StorageObject;
use App\Services\LocalObjectStore;
use App\Services\S3SignatureService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class S3GatewayController extends Controller
{
    public function __construct(
        private readonly S3SignatureService $signatures,
        private readonly LocalObjectStore $objects,
    ) {}

    public function __invoke(Request $request): Response|StreamedResponse
    {
        $requestId = $request->attributes->get('api_request_id', (string) Str::uuid());

        try {
            $accessKey = $this->signatures->authenticate($request);
            $request->attributes->set('s3_access_key', $accessKey);
            $bucket = $this->bucket($request->route('bucket'), $accessKey);
            $key = (string) ($request->route('key') ?? '');
            $response = $this->dispatch($request, $bucket, $key);
            $response->headers->set('x-amz-request-id', $requestId);
            return $response;
        } catch (S3Exception $exception) {
            return $this->error($exception, $requestId);
        } catch (Throwable $exception) {
            report($exception);
            return $this->error(new S3Exception('InternalError', 'We encountered an internal error. Please try again.', 500), $requestId);
        }
    }

    private function dispatch(Request $request, ?StorageBucket $bucket, string $key): Response|StreamedResponse
    {
        if (! $bucket) return $this->listBuckets($request);
        if ($key === '') {
            return match ($request->method()) {
                'PUT' => $this->bucketCreated($bucket),
                'HEAD' => response('', 200),
                'GET' => $this->listObjects($request, $bucket),
                'DELETE' => $this->deleteBucket($bucket),
                default => throw new S3Exception('MethodNotAllowed', 'The requested method is not supported for this resource.', 405),
            };
        }

        return match ($request->method()) {
            'PUT' => $this->putObject($request, $bucket, $key),
            'GET' => $this->getObject($request, $bucket, $key),
            'HEAD' => $this->headObject($bucket, $key),
            'DELETE' => $this->deleteObject($bucket, $key),
            default => throw new S3Exception('MethodNotAllowed', 'The requested method is not supported for this resource.', 405),
        };
    }

    private function bucket(?string $name, StorageAccessKey $accessKey): ?StorageBucket
    {
        if (! $name) return null;
        $bucket = StorageBucket::query()->where('name', $name)->where('status', StorageBucket::STATUS_ACTIVE)->first();
        if (! $bucket) throw new S3Exception('NoSuchBucket', 'The specified bucket does not exist.', 404, $name);
        if ((int) $bucket->project_id !== (int) $accessKey->project_id) throw new S3Exception('AccessDenied', 'You do not have access to this bucket.', 403, $name);
        return $bucket;
    }

    private function listBuckets(Request $request): Response
    {
        $accessKey = $request->attributes->get('s3_access_key');
        $buckets = StorageBucket::query()->where('project_id', $accessKey->project_id)->where('status', StorageBucket::STATUS_ACTIVE)->orderBy('name')->get();
        $xml = '<ListAllMyBucketsResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/"><Owner><ID>'.e((string) $accessKey->project_id).'</ID></Owner><Buckets>';
        foreach ($buckets as $bucket) $xml .= '<Bucket><Name>'.e($bucket->name).'</Name><CreationDate>'.e($bucket->created_at->toIso8601String()).'</CreationDate></Bucket>';
        return $this->xml($xml.'</Buckets></ListAllMyBucketsResult>');
    }

    private function bucketCreated(StorageBucket $bucket): Response { return response('', 200); }

    private function listObjects(Request $request, StorageBucket $bucket): Response
    {
        $max = min(max((int) $request->query('max-keys', 1000), 0), 1000);
        $prefix = (string) $request->query('prefix', '');
        $objects = $bucket->objects()->where('object_key', 'like', $prefix.'%')->orderBy('object_key')->limit($max)->get();
        $xml = '<ListBucketResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/"><Name>'.e($bucket->name).'</Name><Prefix>'.e($prefix).'</Prefix><KeyCount>'.$objects->count().'</KeyCount><MaxKeys>'.$max.'</MaxKeys><IsTruncated>false</IsTruncated>';
        foreach ($objects as $object) $xml .= '<Contents><Key>'.e($object->object_key).'</Key><LastModified>'.e($object->updated_at->toIso8601String()).'</LastModified><ETag>&quot;'.e($object->etag).'&quot;</ETag><Size>'.$object->size_bytes.'</Size><StorageClass>STANDARD</StorageClass></Contents>';
        return $this->xml($xml.'</ListBucketResult>');
    }

    private function putObject(Request $request, StorageBucket $bucket, string $key): Response
    {
        $existing = $bucket->objects()->where('object_key_hash', hash('sha256', $key))->first();
        $stream = $request->getContent(true);
        $stored = $this->objects->put($bucket, $key, $stream);
        $object = DB::transaction(function () use ($bucket, $key, $existing, $stored, $request): StorageObject {
            $object = $existing ?: new StorageObject(['storage_bucket_id' => $bucket->id, 'object_key' => $key, 'object_key_hash' => hash('sha256', $key)]);
            $oldSize = (int) $object->size_bytes;
            $object->fill(['size_bytes' => $stored['size_bytes'], 'etag' => $stored['etag'], 'content_type' => $request->header('Content-Type'), 'metadata' => collect($request->headers->all())->filter(fn ($v, $k) => str_starts_with(strtolower($k), 'x-amz-meta-'))->map(fn ($v) => is_array($v) ? $v[0] : $v)->all(), 'storage_path' => $stored['path']])->save();
            $bucket->increment('usage_bytes', $stored['size_bytes'] - $oldSize);
            if (! $existing) $bucket->increment('object_count');
            return $object;
        });
        return response('', 200)->header('ETag', '"'.$object->etag.'"');
    }

    private function getObject(Request $request, StorageBucket $bucket, string $key): StreamedResponse
    {
        $object = $this->object($bucket, $key);
        $stream = $this->objects->stream($object);
        return response()->stream(function () use ($stream): void { fpassthru($stream); fclose($stream); }, 200, ['Content-Type' => $object->content_type ?: 'application/octet-stream', 'Content-Length' => (string) $object->size_bytes, 'ETag' => '"'.$object->etag.'"', 'Accept-Ranges' => 'bytes']);
    }

    private function headObject(StorageBucket $bucket, string $key): Response
    {
        $object = $this->object($bucket, $key);
        return response('', 200)->header('Content-Type', $object->content_type ?: 'application/octet-stream')->header('Content-Length', $object->size_bytes)->header('ETag', '"'.$object->etag.'"');
    }

    private function deleteObject(StorageBucket $bucket, string $key): Response
    {
        $object = $bucket->objects()->where('object_key_hash', hash('sha256', $key))->first();
        if ($object) { $this->objects->delete($object); $bucket->decrement('usage_bytes', $object->size_bytes); $bucket->decrement('object_count'); $object->delete(); }
        return response('', 204);
    }

    private function deleteBucket(StorageBucket $bucket): Response
    {
        if ($bucket->objects()->exists()) throw new S3Exception('BucketNotEmpty', 'The bucket you tried to delete is not empty.', 409, $bucket->name);
        $bucket->delete();
        return response('', 204);
    }

    private function object(StorageBucket $bucket, string $key): StorageObject
    {
        $object = $bucket->objects()->where('object_key_hash', hash('sha256', $key))->where('object_key', $key)->first();
        if (! $object) throw new S3Exception('NoSuchKey', 'The specified key does not exist.', 404, $bucket->name.'/'.$key);
        return $object;
    }

    private function xml(string $xml): Response { return response($xml, 200)->header('Content-Type', 'application/xml'); }

    private function error(S3Exception $exception, string $requestId): Response
    {
        $resource = $exception->resource ? '<Resource>'.e($exception->resource).'</Resource>' : '';
        $xml = '<Error><Code>'.e($exception->errorCode).'</Code><Message>'.e($exception->getMessage()).'</Message><RequestId>'.e($requestId).'</RequestId>'.$resource.'</Error>';
        return response($xml, $exception->status)->header('Content-Type', 'application/xml')->header('x-amz-request-id', $requestId);
    }
}
