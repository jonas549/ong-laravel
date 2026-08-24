<?php

namespace App\Http\Controllers;

use App\Models\Post;

class PostController extends Controller
{
    public function index()
    {
        return view('public.posts.index', [
            'noticias' => Post::published()->latest('published_at')->paginate(9),
        ]);
    }

    public function show(Post $post)
    {
        abort_unless($post->activo && $post->published_at, 404);

        return view('public.posts.show', [
            'post' => $post,
            'otras' => Post::published()->where('id', '!=', $post->id)->latest('published_at')->take(3)->get(),
        ]);
    }
}
