<?php

declare(strict_types=1);

namespace Tests\Feature;

use System\Testing\HttpTestCase;

class BlogTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    public function testBlogAdminCrudLifecycle(): void
    {
        // 1. Create a post
        $postData = [
            'title' => 'Getting Started with Bongo MVC',
            'content' => '<p>Bongo MVC is a blazing fast PHP framework with zero mandatory dependencies.</p><p><img src="/storage/test.png" alt="Test Image"></p>',
            'featured_image' => 'https://via.placeholder.com/800x400.png',
            'status' => 'published',
        ];

        $this->submit('/posts/create', '/posts', $postData)->assertRedirect('/posts');

        $post = $this->db('posts')->where('title', 'Getting Started with Bongo MVC')->first();
        $this->assertNotNull($post);
        $this->assertSame('getting-started-with-bongo-mvc', $post->slug);
        $this->assertSame('published', $post->status);

        // 2. Listing shows post
        $this->get('/posts')
            ->assertStatus(200)
            ->assertSee('Getting Started with Bongo MVC')
            ->assertSee('getting-started-with-bongo-mvc');

        // 3. Edit form loads
        $this->get("/posts/{$post->id}/edit")
            ->assertStatus(200)
            ->assertSee('Getting Started with Bongo MVC')
            ->assertSee('bongoFileManager')
            ->assertSee('bongoTinyMCEPicker');

        // 4. Update post
        $this->submit("/posts/{$post->id}/edit", "/posts/{$post->id}", [
            '_method' => 'PUT',
            'title' => 'Updated Bongo Post',
            'content' => '<p>Updated content with rich text and images.</p>',
            'featured_image' => '/storage/featured.jpg',
            'status' => 'published',
        ])->assertRedirect('/posts');

        $updated = $this->db('posts')->where('id', $post->id)->first();
        $this->assertSame('Updated Bongo Post', $updated->title);
        $this->assertSame('updated-bongo-post', $updated->slug);

        // 5. Public frontend blog index & show
        $this->get('/blog')
            ->assertStatus(200)
            ->assertSee('Our Blog')
            ->assertSee('Updated Bongo Post');

        $this->get('/blog/' . $updated->slug)
            ->assertStatus(200)
            ->assertSee('Updated Bongo Post')
            ->assertSee('Updated content with rich text and images.');

        // 6. Delete post
        $this->submit('/posts', "/posts/{$post->id}", [
            '_method' => 'DELETE',
        ])->assertRedirect('/posts');

        $this->assertNull($this->db('posts')->where('id', $post->id)->first());
    }

    public function testDraftPostHiddenFromFrontend(): void
    {
        $this->submit('/posts/create', '/posts', [
            'title' => 'Secret Draft Post',
            'content' => '<p>This should not appear publicly.</p>',
            'status' => 'draft',
        ]);

        $draft = $this->db('posts')->where('title', 'Secret Draft Post')->first();
        $this->assertNotNull($draft);

        // Clear session cookies so admin flash message doesn't carry over into public storefront
        $this->cookies = [];

        // Frontend index should not display it
        $this->get('/blog')
            ->assertStatus(200)
            ->assertDontSee('Secret Draft Post');

        // Frontend show should return 404
        $this->get('/blog/' . $draft->slug)
            ->assertStatus(404);
    }

    public function testFileManagerRoutesAndScriptHelper(): void
    {
        // Standalone file manager page loads
        $this->get('/filemanager')->assertStatus(200)->assertSee('File Manager');

        // /filemanager/script endpoint returns javascript
        $scriptRes = $this->get('/filemanager/script');
        $this->assertSame(200, $scriptRes->status);
        $this->assertContains('bongoFileManager', $scriptRes->body);
        $this->assertContains('bongoTinyMCEPicker', $scriptRes->body);
    }

    public function testBlogPostHtmlXssSanitization(): void
    {
        $maliciousPayload = '<p>Safe intro</p><script>alert("xss")</script><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">bad link</a>';

        $this->submit('/posts/create', '/posts', [
            'title' => 'Security Audit Test Post',
            'content' => $maliciousPayload,
            'status' => 'published',
        ])->assertRedirect('/posts');

        $post = $this->db('posts')->where('title', 'Security Audit Test Post')->first();
        $this->assertNotNull($post);

        // Verify stored content does not contain dangerous vectors
        $this->assertNotContains('<script', $post->content);
        $this->assertNotContains('onerror', $post->content);
        $this->assertNotContains('javascript:', $post->content);
        $this->assertContains('<p>Safe intro</p>', $post->content);

        // Verify frontend display
        $res = $this->get('/blog/' . $post->slug);
        $res->assertStatus(200);
        $this->assertNotContains('alert("xss")', $res->body);
        $this->assertNotContains('onerror="alert(1)"', $res->body);
        $this->assertNotContains('href="javascript:alert(1)"', $res->body);
        $this->assertContains('<p>Safe intro</p>', $res->body);
    }
}

