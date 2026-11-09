@extends('theme::layout')

@section('title', $post->title)

@section('content')
    <article>
        <h1>{{ $post->title }}</h1>

        {{-- Nội dung đã được lọc HTML khi lưu (mews/purifier, ticket trang quản trị). --}}
        <div class="post-content">{!! $post->content !!}</div>
    </article>
@endsection
