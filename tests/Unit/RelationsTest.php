<?php

namespace Tests\Unit\RelationsProbe {
    use System\Database\Model;

    class Writer extends Model
    {
        protected static $table = 'rel_writers';
        protected array $fillable = ['name'];
        protected array $casts = ['id' => 'int'];
        public function bio()
        {
            return $this->hasOne(Bio::class);
        }
        public function genres()
        {
            return $this->belongsToMany(Genre::class, 'rel_genre_writer');
        }
    }

    class Bio extends Model
    {
        protected static $table = 'rel_bios';
        protected array $fillable = ['writer_id', 'text'];
    }

    class Genre extends Model
    {
        protected static $table = 'rel_genres';
        protected array $fillable = ['name'];
        protected array $casts = ['id' => 'int'];
    }
}

namespace Tests\Unit {

    use System\Database\Schema\Blueprint;
    use System\Database\Schema\Schema;
    use System\Testing\TestCase;
    use Tests\Unit\RelationsProbe\Bio;
    use Tests\Unit\RelationsProbe\Genre;
    use Tests\Unit\RelationsProbe\Writer;

    class RelationsTest extends TestCase
    {
        public function setUp(): void
        {
            $this->tearDown();
            Schema::create('rel_writers', function (Blueprint $t) {
                $t->id();
                $t->string('name');
            });
            Schema::create('rel_bios', function (Blueprint $t) {
                $t->id();
                $t->integer('writer_id');
                $t->string('text');
            });
            Schema::create('rel_genres', function (Blueprint $t) {
                $t->id();
                $t->string('name');
            });
            Schema::create('rel_genre_writer', function (Blueprint $t) {
                $t->integer('writer_id');
                $t->integer('genre_id');
            });
        }

        public function tearDown(): void
        {
            foreach (['rel_genre_writer', 'rel_genres', 'rel_bios', 'rel_writers'] as $table) {
                Schema::dropIfExists($table);
            }
        }

        public function testHasOne(): void
        {
            $ana = Writer::create(['name' => 'Ana']);
            $ben = Writer::create(['name' => 'Ben']);
            Bio::create(['writer_id' => $ana->id, 'text' => 'Poet from Dhaka']);

            $this->assertSame('Poet from Dhaka', $ana->bio->text);
            $this->assertSame(null, $ben->bio, 'no row: null');

            $writers = Writer::with('bio')->orderBy('id')->get();
            $this->assertSame('Poet from Dhaka', $writers[0]->bio->text, 'eager loaded');
            $this->assertSame(null, $writers[1]->bio);
        }

        public function testBelongsToMany(): void
        {
            $ana = Writer::create(['name' => 'Ana']);
            $ben = Writer::create(['name' => 'Ben']);
            [$poetry, $drama, $essay] = [Genre::create(['name' => 'Poetry']), Genre::create(['name' => 'Drama']), Genre::create(['name' => 'Essay'])];

            $ana->genres()->attach([$poetry->id, $drama->id]);
            $ana->genres()->attach($poetry->id);   // already linked: skipped
            $this->assertSame(['Drama', 'Poetry'], $this->names($ana->genres()->orderBy('name')->get()));

            $this->assertSame(['attached' => [$essay->id], 'detached' => [$poetry->id]], $ana->genres()->sync([$drama->id, $essay->id]));
            $ben->genres()->attach($essay->id);

            $writers = Writer::with('genres')->orderBy('id')->get();
            $this->assertSame(['Drama', 'Essay'], $this->names($writers[0]->genres), 'eager loaded per writer');
            $this->assertSame(['Essay'], $this->names($writers[1]->genres));
            $this->assertSame(false, array_key_exists('__pivot_parent', $writers[0]->genres[0]->toArray()), 'no helper column left over');

            $ana->genres()->detach($drama->id);
            $this->assertSame(['Essay'], $this->names(Writer::find($ana->id)->genres));
            $ana->genres()->detach();
            $this->assertSame([], Writer::find($ana->id)->genres);
        }

        protected function names(array $models): array
        {
            $names = array_map(fn($m) => $m->name, $models);
            sort($names);
            return $names;
        }
    }
}
