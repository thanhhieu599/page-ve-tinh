<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Contracts\View\View;

class PostController extends Controller
{
    public function show(string $slug): View
    {
        $post = Post::query()->published()->where('slug', $slug)->firstOrFail();

        return view('theme::posts.show', ['post' => $post]);
    }
}
