@props(['text'])
<div class="announcement-text text-break" style="white-space: pre-line">@foreach(preg_split('~(https?://[^\s<>"\x27]+)~u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) as $part)@php
    $url = rtrim($part, '.,;:!?)]}');
    $isLink = preg_match('~^https?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL);
@endphp@if($isLink)<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="fw-semibold text-decoration-underline">{{ $url }}</a>{{ substr($part, strlen($url)) }}@else{{ $part }}@endif@endforeach</div>
