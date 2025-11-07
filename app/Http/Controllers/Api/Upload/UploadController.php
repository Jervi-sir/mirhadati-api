<?php

namespace App\Http\Controllers\Api\Upload;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function toiletPhoto(Request $r)
    {
        $validated = validator($r->all(), [
            'file'      => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:6144'],
            'files'     => ['nullable', 'array', 'max:20'],
            'files.*'   => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:6144'],
            'subdir'    => ['nullable', 'string', 'max:50'],
        ])->validate();

        $disk = 'public';
        $subdir = $validated['subdir'] ?? 'toilets';
        $yyyymm = now()->format('Y/m');

        $files = [];
        if ($r->hasFile('file'))  $files[] = $r->file('file');
        if ($r->hasFile('files')) $files = array_merge($files, $r->file('files'));

        if (!$files) return response()->json(['message' => 'No file provided'], 422);

        $out = [];
        foreach ($files as $f) {
            $ext = match ($f->getMimeType()) {
                'image/webp' => 'webp',
                'image/png'  => 'png',
                default      => 'jpg'
            };
            $name = Str::uuid().'.'.$ext;
            $path = "$subdir/$yyyymm/$name";

            $stream = fopen($f->getRealPath(), 'r');
            Storage::disk($disk)->put($path, $stream, ['visibility' => 'public']);
            if (is_resource($stream)) fclose($stream);

            $out[] = [
                'url'  => Storage::disk($disk)->url($path),
                'path' => $path,
                'mime' => $f->getMimeType(),
                'size' => $f->getSize(),
            ];
        }

        return response()->json(['data' => $out], 201);
    }
}
