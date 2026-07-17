<?php

namespace App\Services;

use App\Models\StorageBucket;
use App\Models\StorageMultipartUpload;
use App\Models\StorageMultipartPart;
use App\Models\StorageObject;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class LocalObjectStore
{
    public function put(StorageBucket $bucket, string $key, $stream): array
    {
        $directory = $this->directory($bucket);
        File::ensureDirectoryExists($directory);
        $temporary = $directory.'/'.Str::uuid().'.upload';
        return $this->writeStream($bucket, $key, $stream, $temporary);
    }

    public function putPart(StorageMultipartUpload $upload, int $partNumber, $stream): array
    {
        $path = rtrim(config('storage.object_root'), '/').'/'.$upload->storage_bucket_id.'/multipart/'.$upload->upload_id.'/part-'.$partNumber;
        File::ensureDirectoryExists(dirname($path));

        return $this->writeStreamToPath($path, $stream) + ['storage_path' => $path];
    }

    public function complete(StorageBucket $bucket, StorageMultipartUpload $upload, $parts): array
    {
        $directory = $this->directory($bucket);
        File::ensureDirectoryExists($directory);
        $temporary = $directory.'/'.Str::uuid().'.complete';
        $output = fopen($temporary, 'wb');
        $hash = hash_init('md5');
        $size = 0;

        foreach ($parts as $part) {
            $input = fopen($part->storage_path, 'rb');
            if ($input === false) throw new RuntimeException('A multipart part is not readable.');
            while (! feof($input)) {
                $chunk = fread($input, 1024 * 1024);
                if ($chunk === false) throw new RuntimeException('Unable to read a multipart part.');
                if ($chunk === '') continue;
                fwrite($output, $chunk);
                hash_update($hash, $chunk);
                $size += strlen($chunk);
            }
            fclose($input);
        }
        fclose($output);
        $target = $this->path($bucket, $upload->object_key);
        File::ensureDirectoryExists(dirname($target));
        rename($temporary, $target);
        File::deleteDirectory(dirname($parts->first()->storage_path));

        return ['path' => $target, 'size_bytes' => $size, 'etag' => hash_final($hash)];
    }

    public function delete(StorageObject $object): void
    {
        File::delete($object->storage_path);
    }

    public function deletePart(StorageMultipartPart $part): void
    {
        File::delete($part->storage_path);
    }

    public function stream(StorageObject $object)
    {
        $stream = fopen($object->storage_path, 'rb');
        if ($stream === false) throw new RuntimeException('The object is not readable.');
        return $stream;
    }

    public function path(StorageBucket $bucket, string $key): string
    {
        $key = ltrim($key, '/');
        abort_if($key === '' || str_contains($key, '..') || str_contains($key, "\\"), 400);
        return $this->directory($bucket).'/'.$key;
    }

    private function directory(StorageBucket $bucket): string
    {
        return rtrim(config('storage.object_root'), '/').'/'.$bucket->getKey();
    }

    private function writeStream(StorageBucket $bucket, string $key, $stream, string $temporary): array
    {
        $target = $this->path($bucket, $key);
        $stored = $this->writeStreamToPath($temporary, $stream);
        File::ensureDirectoryExists(dirname($target));
        rename($temporary, $target);

        return $stored + ['path' => $target];
    }

    private function writeStreamToPath(string $path, $stream): array
    {
        $output = fopen($path, 'wb');
        if ($output === false) throw new RuntimeException('Unable to open the object storage path.');
        $hash = hash_init('md5');
        $size = 0;

        while (! feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);
            if ($chunk === false) throw new RuntimeException('Unable to read the upload stream.');
            if ($chunk === '') continue;
            fwrite($output, $chunk);
            hash_update($hash, $chunk);
            $size += strlen($chunk);
        }
        fclose($output);

        return ['size_bytes' => $size, 'etag' => hash_final($hash)];
    }
}
