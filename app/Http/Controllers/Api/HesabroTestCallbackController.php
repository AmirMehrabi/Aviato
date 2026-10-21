<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HesabroTestCallbackController extends Controller
{
    public function __invoke(Request $request, string $token): JsonResponse
    {
        $disk = Storage::disk('local');
        $directory = 'hesabro-tests/'.$token;
        abort_unless($disk->exists($directory.'/session.json'), 404);
        $session = json_decode($disk->get($directory.'/session.json'), true);
        abort_if(now()->timestamp >= $session['expires_at'], 410);
        abort_if(strlen($request->getContent()) > 65536, 413);

        $stored = $disk->append($directory.'/callbacks.jsonl', json_encode([
            'received_at' => now()->toIso8601String(),
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'query' => $request->query(),
            'body' => $request->post(),
            'raw_body' => $request->getContent(),
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
        abort_unless($stored, 500);

        return response()->json(['received' => true, 'message' => 'Test callback saved for review. No wallet was credited.'])
            ->header('Cache-Control', 'no-store');
    }
}
