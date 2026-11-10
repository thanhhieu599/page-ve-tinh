<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\ContentImages;
use Illuminate\Contracts\View\View;

class PostController extends Controller
{
    public function show(string $slug): View
    {
        $post = Post::query()->published()->where('slug', $slug)
            ->with(['author', 'faqs', 'categories.parent', 'products' => fn ($query) => $query->active()])
            ->firstOrFail();

        return view('theme::posts.show', [
            'post' => $post,
            'content' => ContentImages::enhance($post->content, $post->site_id),
        ]);
    }
}
