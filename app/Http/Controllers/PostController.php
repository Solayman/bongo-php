<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use System\Http\Request;

class PostController extends Controller
{
    /**
     * Admin post listing
     */
    public function index(Request $request)
    {
        $search = $request->q;
        $status = $request->status;

        $query = Post::query()->orderBy('id', 'DESC');

        if ($search) {
            $query->whereRaw('title LIKE ? OR content LIKE ?', ["%$search%", "%$search%"]);
        }

        if (in_array($status, ['published', 'draft'], true)) {
            $query->where('status', $status);
        }

        return $this->view('posts.index', [
            'posts' => $query->paginate(10),
            'filters' => ['q' => $search, 'status' => $status],
        ]);
    }

    /**
     * Create post form
     */
    public function create()
    {
        return $this->view('posts.form', [
            'post' => null,
        ]);
    }

    /**
     * Store post
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'featured_image' => 'nullable|string|max:255',
            'status' => 'required|in:published,draft',
        ]);

        $data['slug'] = Post::uniqueSlug($data['title']);
        $data['user_id'] = auth()->id ?? null;

        $post = Post::create($data);

        flash('success', "Post \"{$post->title}\" created successfully.");
        redirect(url('/posts'));
    }

    /**
     * Edit post form
     */
    public function edit($id)
    {
        $post = Post::findOrFail($id);

        return $this->view('posts.form', [
            'post' => $post,
        ]);
    }

    /**
     * Update post
     */
    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'featured_image' => 'nullable|string|max:255',
            'status' => 'required|in:published,draft',
        ]);

        if ($data['title'] !== $post->title) {
            $data['slug'] = Post::uniqueSlug($data['title'], (int) $post->id);
        }

        $post->update($data);

        flash('success', "Post \"{$post->title}\" updated successfully.");
        redirect(url('/posts'));
    }

    /**
     * Delete post
     */
    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        $post->delete();

        flash('success', "Post \"{$post->title}\" deleted.");
        redirect(url('/posts'));
    }
}
