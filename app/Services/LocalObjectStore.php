<?php

namespace App\Services;

use App\Models\StorageBucket;
use App\Models\StorageObject;
use Illuminate\Http\UploadedFile;
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
        $target = $this->path($bucket, $key);
        File::ensureDirectoryExists(dirname($target));
        $output = fopen($temporary, 'wb');
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
        File::ensureDirectoryExists(dirname($target));
        rename($temporary, $target);

        return ['path' => $target, 'size_bytes' => $size, 'etag' => hash_final($hash)];
    }

    public function delete(StorageObject $object): void
    {
        File::delete($object->storage_path);
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
}
