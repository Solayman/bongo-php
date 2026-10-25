<?php

namespace Tests\Unit;

use DateTime;
use DateTimeZone;
use System\Database\Model;
use System\Support\Date;
use System\Testing\TestCase;

class DateProbeModel extends Model
{
    protected static $table = 'date_probe_models';
    protected array $fillable = ['name', 'published_at', 'event_date', 'created_at', 'updated_at', 'deleted_at'];
    protected array $casts = [
        'published_at' => 'datetime',
        'event_date'   => 'date',
    ];
}

class DateTest extends TestCase
{
    public function testNowAndTodayHelpers(): void
    {
        $now = now();
        $this->assertTrue($now instanceof Date);

        $today = today();
        $this->assertTrue($today instanceof Date);
        $this->assertSame('00:00:00', $today->format('H:i:s'));
        $this->assertSame($today->toDateString(), $today->format('Y-m-d'));
    }

    public function testDateParseVariantsAndTimezone(): void
    {
        $d1 = Date::parse('2026-10-25 12:00:00', 'UTC');
        $this->assertSame('UTC', $d1->getTimezone()->getName());
        $this->assertSame('2026-10-25 12:00:00', $d1->toDateTimeString());

        // Parse integer timestamp
        $timestamp = 1729857600;
        $d2 = Date::parse($timestamp, 'UTC');
        $this->assertSame($timestamp, $d2->getTimestamp());

        // Parse DateTimeInterface and shift timezone
        $nativeDt = new DateTime('2026-10-25 12:00:00', new DateTimeZone('UTC'));
        $d3 = Date::parse($nativeDt, 'Asia/Dhaka');
        $this->assertSame('Asia/Dhaka', $d3->getTimezone()->getName());
        $this->assertSame('2026-10-25 18:00:00', $d3->toDateTimeString());
    }

    public function testAddAndSubHelpers(): void
    {
        $d = Date::parse('2026-10-25 10:00:00', 'UTC');

        $this->assertSame('2026-10-27 10:00:00', $d->addDays(2)->toDateTimeString());
        $this->assertSame('2026-10-24 10:00:00', $d->subDays(1)->toDateTimeString());
        $this->assertSame('2026-10-25 13:00:00', $d->addHours(3)->toDateTimeString());
        $this->assertSame('2026-10-25 08:00:00', $d->subHours(2)->toDateTimeString());
        $this->assertSame('2026-10-25 10:15:00', $d->addMinutes(15)->toDateTimeString());
        $this->assertSame('2026-10-25 00:00:00', $d->startOfDay()->toDateTimeString());
        $this->assertSame('2026-10-25 23:59:59', $d->endOfDay()->toDateTimeString());
    }

    public function testDiffForHumans(): void
    {
        $now = Date::now('UTC');

        $pastHour = $now->subHours(2);
        $this->assertSame('2 hours ago', $pastHour->diffForHumans($now));

        $pastDay = $now->subDays(3);
        $this->assertSame('3 days ago', $pastDay->diffForHumans($now));

        $futureDay = $now->addDays(5);
        $this->assertSame('in 5 days', $futureDay->diffForHumans($now));

        $justNow = $now->subSeconds(2);
        $this->assertSame('just now', $justNow->diffForHumans($now));
    }

    public function testModelTimestampCastingAndJsonSerialization(): void
    {
        $model = new DateProbeModel([
            'name'         => 'Test Model',
            'created_at'   => '2026-10-25 12:00:00',
            'published_at' => '2026-10-25 14:30:00',
            'event_date'   => '2026-10-25',
        ]);

        $this->assertTrue($model->created_at instanceof Date);
        $this->assertTrue($model->published_at instanceof Date);
        $this->assertSame('2026-10-25', $model->event_date);

        $arr = $model->toArray();
        $this->assertContains('2026-10-25T12:00:00', $arr['created_at']);
        $this->assertContains('2026-10-25T14:30:00', $arr['published_at']);
        $this->assertSame('2026-10-25', $arr['event_date']);

        $json = $model->toJson();
        $this->assertContains('2026-10-25T12:00:00', $json);

        // Assigning a DateTimeInterface
        $model->published_at = new DateTime('2026-10-26 09:00:00');
        $this->assertTrue($model->published_at instanceof Date);
        $this->assertSame('2026-10-26 09:00:00', $model->published_at->toDateTimeString());
    }
}
