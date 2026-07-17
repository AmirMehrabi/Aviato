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
        if ($key !== '' && $request->has('uploads')) return $this->initiateMultipart($request, $bucket, $key);
        if ($key !== '' && $request->has('uploadId')) {
            return match ($request->method()) {
                'PUT' => $this->uploadPart($request, $bucket, $key),
                'GET' => $this->listParts($request, $bucket, $key),
                'POST' => $this->completeMultipart($request, $bucket, $key),
                'DELETE' => $this->abortMultipart($request, $bucket, $key),
                default => throw new S3Exception('MethodNotAllowed', 'The requested method is not supported for this resource.', 405),
            };
        }
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
        $startAfter = (string) $request->query('start-after', '');
        if ($request->filled('continuation-token')) $startAfter = base64_decode(strtr((string) $request->query('continuation-token'), '-_', '+/')) ?: '';
        $objects = $bucket->objects()->where('object_key', 'like', $prefix.'%')->when($startAfter !== '', fn ($query) => $query->where('object_key', '>', $startAfter))->orderBy('object_key')->limit($max + 1)->get();
        $truncated = $objects->count() > $max;
        $objects = $objects->take($max);
        $delimiter = (string) $request->query('delimiter', '');
        $commonPrefixes = [];
        $contents = [];
        foreach ($objects as $object) {
            $rest = substr($object->object_key, strlen($prefix));
            if ($delimiter !== '' && str_contains($rest, $delimiter)) {
                $commonPrefixes[$prefix.substr($rest, 0, strpos($rest, $delimiter) + strlen($delimiter))] = true;
                continue;
            }
            $contents[] = $object;
        }
        $nextToken = $truncated && $objects->last() ? rtrim(strtr(base64_encode($objects->last()->object_key), '+/', '-_'), '=') : null;
        $xml = '<ListBucketResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/"><Name>'.e($bucket->name).'</Name><Prefix>'.e($prefix).'</Prefix><KeyCount>'.($contents ? count($contents) : 0).'</KeyCount><MaxKeys>'.$max.'</MaxKeys><IsTruncated>'.($truncated ? 'true' : 'false').'</IsTruncated>'.($nextToken ? '<NextContinuationToken>'.e($nextToken).'</NextContinuationToken>' : '');
        foreach ($commonPrefixes as $commonPrefix => $_) $xml .= '<CommonPrefixes><Prefix>'.e($commonPrefix).'</Prefix></CommonPrefixes>';
        foreach ($contents as $object) $xml .= '<Contents><Key>'.e($object->object_key).'</Key><LastModified>'.e($object->updated_at->toIso8601String()).'</LastModified><ETag>&quot;'.e($object->etag).'&quot;</ETag><Size>'.$object->size_bytes.'</Size><StorageClass>STANDARD</StorageClass></Contents>';
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

    private function initiateMultipart(Request $request, StorageBucket $bucket, string $key): Response
    {
        $upload = $bucket->multipartUploads()->create([
            'upload_id' => (string) Str::uuid(),
            'object_key' => $key,
            'object_key_hash' => hash('sha256', $key),
            'content_type' => $request->header('Content-Type'),
            'metadata' => $this->metadata($request),
            'expires_at' => now()->addHours(config('storage.multipart_expiry_hours', 24)),
        ]);

        return $this->xml('<InitiateMultipartUploadResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/"><Bucket>'.e($bucket->name).'</Bucket><Key>'.e($key).'</Key><UploadId>'.e($upload->upload_id).'</UploadId></InitiateMultipartUploadResult>');
    }

    private function uploadPart(Request $request, StorageBucket $bucket, string $key): Response
    {
        $upload = $this->multipart($request, $bucket, $key);
        $partNumber = filter_var($request->query('partNumber'), FILTER_VALIDATE_INT);
        if ($partNumber === false || $partNumber < 1 || $partNumber > 10000) throw new S3Exception('InvalidPart', 'The part number must be between 1 and 10000.', 400);
        $stored = $this->objects->putPart($upload, $partNumber, $request->getContent(true));
        $part = $upload->parts()->updateOrCreate(['part_number' => $partNumber], $stored);

        return response('', 200)->header('ETag', '"'.$part->etag.'"');
    }

    private function listParts(Request $request, StorageBucket $bucket, string $key): Response
    {
        $upload = $this->multipart($request, $bucket, $key);
        $parts = $upload->parts()->orderBy('part_number')->get();
        $xml = '<ListPartsResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/"><Bucket>'.e($bucket->name).'</Bucket><Key>'.e($key).'</Key><UploadId>'.e($upload->upload_id).'</UploadId><IsTruncated>false</IsTruncated>';
        foreach ($parts as $part) $xml .= '<Part><PartNumber>'.$part->part_number.'</PartNumber><LastModified>'.e($part->updated_at->toIso8601String()).'</LastModified><ETag>&quot;'.e($part->etag).'&quot;</ETag><Size>'.$part->size_bytes.'</Size></Part>';
        return $this->xml($xml.'</ListPartsResult>');
    }

    private function completeMultipart(Request $request, StorageBucket $bucket, string $key): Response
    {
        $upload = $this->multipart($request, $bucket, $key);
        $requested = simplexml_load_string((string) $request->getContent());
        if ($requested === false) throw new S3Exception('MalformedXML', 'The multipart completion XML is invalid.', 400);
        $partNumbers = collect($requested->Part ?? [])->map(fn ($part): int => (int) $part->PartNumber)->filter()->values();
        $parts = $upload->parts()->whereIn('part_number', $partNumbers)->orderBy('part_number')->get();
        if ($partNumbers->isEmpty() || $parts->count() !== $partNumbers->unique()->count()) throw new S3Exception('InvalidPart', 'One or more requested parts are missing.', 400);
        $stored = $this->objects->complete($bucket, $upload, $parts);
        $existing = $bucket->objects()->where('object_key_hash', hash('sha256', $key))->first();
        $object = DB::transaction(function () use ($bucket, $key, $existing, $stored, $upload): StorageObject {
            $object = $existing ?: new StorageObject(['storage_bucket_id' => $bucket->id, 'object_key' => $key, 'object_key_hash' => hash('sha256', $key)]);
            $oldSize = (int) $object->size_bytes;
            $object->fill(['size_bytes' => $stored['size_bytes'], 'etag' => $stored['etag'], 'content_type' => $upload->content_type, 'metadata' => $upload->metadata, 'storage_path' => $stored['path']])->save();
            $bucket->increment('usage_bytes', $stored['size_bytes'] - $oldSize);
            if (! $existing) $bucket->increment('object_count');
            $upload->update(['status' => 'completed']);
            return $object;
        });
        $upload->parts()->delete();

        return $this->xml('<CompleteMultipartUploadResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/"><Location>'.e(config('storage.aviato_endpoint').'/'.$bucket->name.'/'.$key).'</Location><Bucket>'.e($bucket->name).'</Bucket><Key>'.e($key).'</Key><ETag>&quot;'.e($object->etag).'&quot;</ETag></CompleteMultipartUploadResult>');
    }

    private function abortMultipart(Request $request, StorageBucket $bucket, string $key): Response
    {
        $upload = $this->multipart($request, $bucket, $key);
        foreach ($upload->parts as $part) $this->objects->deletePart($part);
        $upload->delete();
        return response('', 204);
    }

    private function multipart(Request $request, StorageBucket $bucket, string $key): \App\Models\StorageMultipartUpload
    {
        $upload = $bucket->multipartUploads()->where('upload_id', (string) $request->query('uploadId'))->where('object_key', $key)->where('status', 'active')->first();
        if (! $upload || $upload->expires_at->isPast()) throw new S3Exception('NoSuchUpload', 'The specified multipart upload does not exist.', 404);
        return $upload;
    }

    private function metadata(Request $request): array
    {
        return collect($request->headers->all())->filter(fn ($v, $k) => str_starts_with(strtolower($k), 'x-amz-meta-'))->map(fn ($v) => is_array($v) ? $v[0] : $v)->all();
    }

    private function getObject(Request $request, StorageBucket $bucket, string $key): StreamedResponse
    {
        $object = $this->object($bucket, $key);
        $etag = '"'.$object->etag.'"';
        if ($request->header('If-None-Match') === $etag || $request->header('If-None-Match') === $object->etag) return response('', 304)->header('ETag', $etag);
        if ($request->hasHeader('If-Match') && trim((string) $request->header('If-Match'), '"') !== $object->etag) throw new S3Exception('PreconditionFailed', 'The object ETag does not match the If-Match condition.', 412);
        $start = 0;
        $end = $object->size_bytes - 1;
        $status = 200;
        $range = $request->header('Range');
        if ($range !== null) {
            if (! preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $matches) || ($matches[1] === '' && $matches[2] === '')) throw new S3Exception('InvalidRange', 'Only a single byte range is supported.', 416);
            if ($matches[1] === '') { $length = (int) $matches[2]; $start = max(0, $object->size_bytes - $length); } else { $start = (int) $matches[1]; }
            if ($matches[2] !== '') $end = (int) $matches[2];
            if ($start > $end || $start >= $object->size_bytes) throw new S3Exception('InvalidRange', 'The requested range is not satisfiable.', 416);
            $end = min($end, $object->size_bytes - 1);
            $status = 206;
        }
        $stream = $this->objects->stream($object);
        return response()->stream(function () use ($stream, $start, $end): void { fseek($stream, $start); $remaining = $end - $start + 1; while ($remaining > 0 && ! feof($stream)) { $chunk = fread($stream, min(1024 * 1024, $remaining)); if ($chunk === false || $chunk === '') break; echo $chunk; $remaining -= strlen($chunk); } fclose($stream); }, $status, ['Content-Type' => $object->content_type ?: 'application/octet-stream', 'Content-Length' => (string) ($end - $start + 1), 'Content-Range' => $status === 206 ? 'bytes '.$start.'-'.$end.'/'.$object->size_bytes : null, 'ETag' => $etag, 'Accept-Ranges' => 'bytes']);
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
