<?php

namespace App\Support;

class DocumentByteRanges
{
    public static function guard(string $path, bool $video): void
    {
        if (! $video || ! request()->isMethod('GET') || ! request()->headers->has('Range')) {
            return;
        }
        $range = (string) request()->header('Range');
        $size = filesize($path);
        $valid = preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $parts) === 1;
        if ($valid) {
            $start = $parts[1];
            $end = $parts[2];
            $valid = $start === '' ? $end !== '' && (float) $end > 0
                : (float) $start < $size && ($end === '' || (float) $end >= (float) $start);
            if ($valid && $start === '' && (float) $end >= $size) {
                request()->headers->set('Range', 'bytes=0-');
            }
        }
        abort_unless($valid, 416, 'Rentang video tidak tersedia.', ['Content-Range' => 'bytes */'.$size]);
    }
}
