<?php

namespace Tests\Unit\SoftProbe {
    use System\Database\Model;

    class Note extends Model
    {
        protected static $table = 'soft_notes';
        protected static bool $softDeletes = true;
        protected array $fillable = ['title', 'pinned'];
        protected array $casts = ['id' => 'int'];
    }
}

namespace Tests\Unit {

    use System\Database\DB;
    use System\Database\Schema\Blueprint;
    use System\Database\Schema\Schema;
    use System\Testing\TestCase;
    use Tests\Unit\SoftProbe\Note;

    class SoftDeletesTest extends TestCase
    {
        public function setUp(): void
        {
            Schema::dropIfExists('soft_notes');
            Schema::create('soft_notes', function (Blueprint $t) {
                $t->id();
                $t->string('title');
                $t->integer('pinned')->default(0);
                $t->softDeletes();
            });
        }

        public function tearDown(): void
        {
            Schema::dropIfExists('soft_notes');
        }

        public function testDeleteHidesTheRowAndRestoreBringsItBack(): void
        {
            $a = Note::create(['title' => 'A']);
            Note::create(['title' => 'B']);

            $this->assertTrue($a->delete());
            $this->assertTrue($a->trashed());
            $this->assertSame(1, DB::table('soft_notes')->where('id', $a->id)->count(), 'the row is still in the table');
            $this->assertSame(null, Note::find($a->id), 'but queries skip it');
            $this->assertCount(1, Note::all());
            $this->assertSame(1, Note::query()->count());

            $this->assertCount(2, Note::withTrashed()->get());
            $this->assertSame(['A'], array_map(fn($n) => $n->title, Note::onlyTrashed()->get()));

            $a->restore();
            $this->assertFalse($a->trashed());
            $this->assertCount(2, Note::all());
        }

        public function testOrConditionsCannotBringTrashedRowsBack(): void
        {
            $a = Note::create(['title' => 'A', 'pinned' => 1]);
            Note::create(['title' => 'B']);
            $a->delete();
            $titles = array_map(fn($n) => $n->title, Note::where('pinned', 1)->orWhere('title', 'B')->get());
            $this->assertSame(['B'], $titles);
        }

        public function testQueryDeleteDestroyAndForceDelete(): void
        {
            $a = Note::create(['title' => 'A']);
            $b = Note::create(['title' => 'B']);
            $c = Note::create(['title' => 'C']);

            Note::where('title', 'A')->delete();          // a query: soft
            Note::destroy([$b->id]);                      // by id: soft
            $this->assertSame(3, DB::table('soft_notes')->count());
            $this->assertSame(['C'], array_map(fn($n) => $n->title, Note::all()));

            Note::onlyTrashed()->where('title', 'A')->restore();
            $this->assertSame(['A', 'C'], array_map(fn($n) => $n->title, Note::query()->orderBy('title')->get()));

            $c->forceDelete();                            // really gone
            Note::withTrashed()->where('title', 'B')->forceDelete();
            $this->assertSame(1, DB::table('soft_notes')->count());
        }
    }
}
