<?php

namespace Tests\Unit\EventsProbe {
    use System\Database\Model;

    class Post extends Model
    {
        protected static $table = 'ev_posts';
        protected array $fillable = ['title', 'slug', 'status', 'views'];
        protected array $casts = ['id' => 'int', 'views' => 'int'];
        public static array $log = [];

        protected function saving()
        {
            self::$log[] = 'saving';
        }
        protected function creating()
        {
            self::$log[] = 'creating';
            $this->slug ??= strtolower(str_replace(' ', '-', (string) $this->title));
        }
        protected function created()
        {
            self::$log[] = 'created';
        }
        protected function updating()
        {
            self::$log[] = 'updating';
            if ($this->title === 'locked') {
                return false;
            }
        }
        protected function updated()
        {
            self::$log[] = 'updated';
        }
        protected function saved()
        {
            self::$log[] = 'saved';
        }
        protected function deleting()
        {
            self::$log[] = 'deleting';
            if ($this->status === 'keep') {
                return false;
            }
        }
        protected function deleted()
        {
            self::$log[] = 'deleted';
        }

        public function scopePublished($query)
        {
            $query->where('status', 'published');
        }
        public function scopePopular($query, int $min = 100)
        {
            $query->where('views', '>=', $min);
        }
    }
}

namespace Tests\Unit {

    use System\Database\DB;
    use System\Database\Schema\Blueprint;
    use System\Database\Schema\Schema;
    use System\Testing\TestCase;
    use Tests\Unit\EventsProbe\Post;

    class ModelEventsTest extends TestCase
    {
        public function setUp(): void
        {
            Schema::dropIfExists('ev_posts');
            Schema::create('ev_posts', function (Blueprint $t) {
                $t->id();
                $t->string('title');
                $t->string('slug')->nullable();
                $t->string('status')->default('draft');
                $t->integer('views')->default(0);
            });
            Post::$log = [];
        }

        public function tearDown(): void
        {
            Schema::dropIfExists('ev_posts');
        }

        public function testEventsRunInOrderAndCanFillColumns(): void
        {
            $post = Post::create(['title' => 'Hello World']);
            $this->assertSame(['saving', 'creating', 'created', 'saved'], Post::$log);
            $this->assertSame('hello-world', $post->slug, 'creating() filled the slug');
            $this->assertSame('hello-world', DB::table('ev_posts')->where('id', $post->id)->value('slug'));

            Post::$log = [];
            $post->update(['status' => 'published']);
            $this->assertSame(['saving', 'updating', 'updated', 'saved'], Post::$log);

            Post::$log = [];
            $post->delete();
            $this->assertSame(['deleting', 'deleted'], Post::$log);
        }

        public function testReturningFalseCancels(): void
        {
            $post = Post::create(['title' => 'Draft', 'status' => 'keep']);
            $this->assertFalse($post->delete(), 'deleting() said no');
            $this->assertSame(1, DB::table('ev_posts')->count());

            $post->title = 'locked';
            $this->assertFalse($post->save(), 'updating() said no');
            $this->assertSame('Draft', DB::table('ev_posts')->where('id', $post->id)->value('title'));
        }

        public function testScopes(): void
        {
            Post::create(['title' => 'A', 'status' => 'published', 'views' => 500]);
            Post::create(['title' => 'B', 'status' => 'published', 'views' => 5]);
            Post::create(['title' => 'C', 'status' => 'draft', 'views' => 900]);

            $titles = fn(array $posts) => array_map(fn($p) => $p->title, $posts);
            $this->assertSame(['A', 'B'], $titles(Post::published()->orderBy('title')->get()));
            $this->assertSame(['A'], $titles(Post::published()->popular()->get()), 'scopes chain');
            $this->assertSame(['A', 'C'], $titles(Post::popular(300)->orderBy('title')->get()), 'scopes take arguments');
            $this->assertSame(1, Post::where('title', 'C')->popular()->count(), 'after where() too');

            $this->assertThrows(\BadMethodCallException::class, fn() => Post::query()->nonsense());
        }
    }
}
