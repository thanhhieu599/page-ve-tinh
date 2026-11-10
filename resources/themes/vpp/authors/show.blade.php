@extends('theme::layout')

@section('title', $author->name)

@section('content')
    <section class="author-profile">
        <h1>{{ $author->name }}</h1>

        @if ($author->job_title)
            <p class="author-job-title">{{ $author->job_title }}</p>
        @endif

        @if ($author->bio)
            <p>{{ $author->bio }}</p>
        @endif

        @if ($author->expertise)
            <p><strong>Chuyên môn:</strong> {{ $author->expertise }}</p>
        @endif

        @if ($author->linkedin_url)
            <p><a href="{{ $author->linkedin_url }}" rel="noopener me" target="_blank">LinkedIn</a></p>
        @endif

        <h2>Bài viết</h2>
        @include('theme::partials.post-list', ['posts' => $posts])
    </section>
@endsection
