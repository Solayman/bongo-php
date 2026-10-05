<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use System\Http\Request;

class BlogController extends Controller
{
    /**
     * Frontend blog list
     */
    public function index(Request $request)
    {
        $search = $request->q;

        $query = Post::query()
            ->with('user')
            ->where('status', 'published')
            ->orderBy('id', 'DESC');

        if ($search) {
            $query->whereRaw('title LIKE ? OR content LIKE ?', ["%$search%", "%$search%"]);
        }

        return $this->view('blog.index', [
            'posts' => $query->paginate(9),
            'search' => $search,
        ]);
    }

    /**
     * Frontend single post detail
     */
    public function show(string $slug)
    {
        $post = Post::where('slug', $slug)
            ->where('status', 'published')
            ->first();

        if (!$post) {
            abort(404, 'Post not found');
        }

        return $this->view('blog.show', [
            'post' => $post,
        ]);
    }
}
