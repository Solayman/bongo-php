<?php

namespace Tests\Unit\GateProbe {
    class Post
    {
        public function __construct(public int $authorId, public bool $locked = false) {}
    }

    class PostPolicy
    {
        public function before(?object $user, string $ability): ?bool
        {
            return ($user->role ?? null) === 'owner' ? true : null;
        }

        public function update(object $user, Post $post): bool
        {
            return $user->id === $post->authorId && !$post->locked;
        }

        public function forceDelete(object $user, Post $post): bool
        {
            return false;
        }

        public function view(?object $user, Post $post): bool
        {
            return true;   // guests may view
        }
    }
}

namespace Tests\Unit {

    use System\Auth\Gate;
    use System\Http\HttpException;
    use System\Testing\TestCase;
    use Tests\Unit\GateProbe\Post;
    use Tests\Unit\GateProbe\PostPolicy;

    class GateTest extends TestCase
    {
        protected object $alice;
        protected object $bob;

        public function setUp(): void
        {
            Gate::flush();
            $this->alice = (object) ['id' => 1, 'role' => 'admin'];
            $this->bob = (object) ['id' => 2, 'role' => 'customer'];
        }

        public function tearDown(): void
        {
            Gate::flush();
        }

        public function testAbilities(): void
        {
            Gate::define('manage-system', fn($user) => $user->role === 'admin');
            Gate::define('edit-comment', fn($user, $comment) => $user->id === $comment['user_id']);

            Gate::actingAs($this->alice);
            $this->assertTrue(Gate::allows('manage-system'));
            $this->assertTrue(can('manage-system'), 'the can() helper');
            $this->assertFalse(Gate::allows('edit-comment', ['user_id' => 2]));
            $this->assertFalse(Gate::allows('no-such-ability'), 'unknown abilities are denied');

            Gate::actingAs($this->bob);
            $this->assertTrue(Gate::denies('manage-system'));
            $this->assertTrue(Gate::allows('edit-comment', ['user_id' => 2]));

            $this->assertTrue(Gate::forUser($this->alice)->allows('manage-system'), 'check another user');
        }

        public function testGuestsAreDeniedUnlessTheCallbackAcceptsNull(): void
        {
            Gate::define('dashboard', fn($user) => true);
            Gate::define('read-blog', fn(?object $user) => true);
            Gate::actingAs(null);
            $this->assertFalse(Gate::allows('dashboard'));
            $this->assertTrue(Gate::allows('read-blog'));
        }

        public function testAuthorizeThrows403(): void
        {
            Gate::define('manage-system', fn($user) => $user->role === 'admin');
            Gate::actingAs($this->bob);
            try {
                Gate::authorize('manage-system');
                throw new \System\Testing\AssertionFailed('Expected a 403');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->status());
            }
            Gate::actingAs($this->alice);
            Gate::authorize('manage-system');   // no exception
        }

        public function testPolicies(): void
        {
            Gate::policy(Post::class, PostPolicy::class);
            $mine = new Post(2);

            Gate::actingAs($this->bob);
            $this->assertTrue(Gate::allows('update', $mine));
            $this->assertFalse(Gate::allows('update', new Post(2, locked: true)));
            $this->assertFalse(Gate::allows('update', new Post(1)), "someone else's post");
            $this->assertFalse(Gate::allows('force-delete', $mine), '"force-delete" calls forceDelete()');
            $this->assertFalse(Gate::allows('archive', $mine), 'no policy method and no ability: denied');

            Gate::actingAs((object) ['id' => 9, 'role' => 'owner']);
            $this->assertTrue(Gate::allows('force-delete', $mine), "the policy's before() decides first");

            Gate::actingAs(null);
            $this->assertTrue(Gate::allows('view', $mine), 'policy methods with ?object accept guests');
            $this->assertFalse(Gate::allows('update', $mine));
        }

        public function testBeforeCallbacks(): void
        {
            Gate::define('delete-everything', fn($user) => false);
            Gate::before(fn($user, $ability) => $user->role === 'admin' ? true : null);
            Gate::actingAs($this->alice);
            $this->assertTrue(Gate::allows('delete-everything'), 'before() returned true');
            Gate::actingAs($this->bob);
            $this->assertFalse(Gate::allows('delete-everything'), 'before() returned null: the ability decides');
        }

        public function testAppPoliciesAreFoundByName(): void
        {
            Gate::actingAs($this->alice);
            $product = new \App\Models\Product();
            $product->id = 999999;   // never ordered
            $this->assertTrue(Gate::allows('update', $product), 'App\Policies\ProductPolicy found for App\Models\Product');
            $this->assertTrue(Gate::allows('delete', $product));
            Gate::actingAs($this->bob);
            $this->assertFalse(Gate::allows('update', $product));
        }

        public function testCanDirectives(): void
        {
            Gate::define('see', fn($user, $n) => $n > 1);
            Gate::actingAs($this->alice);
            $engine = new \System\View\ViewEngine(sys_get_temp_dir(), sys_get_temp_dir() . '/bongo_can_cache', false);
            $file = sys_get_temp_dir() . '/bongo_can_test.php';
            file_put_contents($file, "@can('see', 2)A@elsecan('see', 3)B@else C@endcan|@cannot('see', 1)D@endcannot");
            try {
                $this->assertSame('A|D', trim($engine->render('bongo_can_test')));
            } finally {
                @unlink($file);
            }
        }
    }
}
