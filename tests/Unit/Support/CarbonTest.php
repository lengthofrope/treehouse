<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use LengthOfRope\TreeHouse\Support\Carbon;
use Tests\TestCase;
use DateTime;
use DateTimeZone;

/**
 * Test cases for Carbon class
 * 
 * @package Tests\Unit\Support
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
class CarbonTest extends TestCase
{
    public function testConstructor(): void
    {
        $carbon = new Carbon();
        $this->assertInstanceOf(Carbon::class, $carbon);
        
        $carbon = new Carbon('2023-01-01 12:00:00');
        $this->assertEquals('2023-01-01 12:00:00', $carbon->format('Y-m-d H:i:s'));
        
        $carbon = new Carbon('2023-01-01 12:00:00', 'UTC');
        $this->assertEquals('UTC', $carbon->getTimezone()->getName());
        
        $timezone = new DateTimeZone('Europe/Amsterdam');
        $carbon = new Carbon('2023-01-01 12:00:00', $timezone);
        $this->assertEquals('Europe/Amsterdam', $carbon->getTimezone()->getName());
    }

    public function testInstance(): void
    {
        $dateTime = new DateTime('2023-01-01 12:00:00');
        $carbon = Carbon::instance($dateTime);
        
        $this->assertInstanceOf(Carbon::class, $carbon);
        $this->assertEquals('2023-01-01 12:00:00', $carbon->format('Y-m-d H:i:s'));
    }

    public function testNow(): void
    {
        $carbon = Carbon::now();
        $this->assertInstanceOf(Carbon::class, $carbon);
        
        $carbon = Carbon::now('UTC');
        $this->assertEquals('UTC', $carbon->getTimezone()->getName());
        
        $timezone = new DateTimeZone('Europe/Amsterdam');
        $carbon = Carbon::now($timezone);
        $this->assertEquals('Europe/Amsterdam', $carbon->getTimezone()->getName());
    }

    public function testToday(): void
    {
        $carbon = Carbon::today();
        $this->assertInstanceOf(Carbon::class, $carbon);
        $this->assertEquals('00:00:00', $carbon->format('H:i:s'));
        $this->assertEquals(date('Y-m-d'), $carbon->format('Y-m-d'));
    }

    public function testTomorrow(): void
    {
        $carbon = Carbon::tomorrow();
        $this->assertInstanceOf(Carbon::class, $carbon);
        $this->assertEquals('00:00:00', $carbon->format('H:i:s'));
        
        $expected = (new DateTime())->modify('+1 day')->format('Y-m-d');
        $this->assertEquals($expected, $carbon->format('Y-m-d'));
    }

    public function testYesterday(): void
    {
        $carbon = Carbon::yesterday();
        $this->assertInstanceOf(Carbon::class, $carbon);
        $this->assertEquals('00:00:00', $carbon->format('H:i:s'));
        
        $expected = (new DateTime())->modify('-1 day')->format('Y-m-d');
        $this->assertEquals($expected, $carbon->format('Y-m-d'));
    }

    public function testCreate(): void
    {
        $carbon = Carbon::create(2023, 1, 15, 14, 30, 45);
        $this->assertEquals('2023-01-15 14:30:45', $carbon->format('Y-m-d H:i:s'));
        
        $carbon = Carbon::create(2023);
        $this->assertEquals('2023-01-01 00:00:00', $carbon->format('Y-m-d H:i:s'));
        
        $carbon = Carbon::create(2023, 6);
        $this->assertEquals('2023-06-01 00:00:00', $carbon->format('Y-m-d H:i:s'));
    }

    public function testCreateFromTimestamp(): void
    {
        $timestamp = 1672574400; // 2023-01-01 12:00:00 UTC
        $carbon = Carbon::createFromTimestamp($timestamp);
        
        $this->assertInstanceOf(Carbon::class, $carbon);
        $this->assertEquals($timestamp, $carbon->getTimestamp());
    }

    public function testCreateFromFormat(): void
    {
        $carbon = Carbon::createFromFormat('Y-m-d H:i:s', '2023-01-01 12:00:00');
        $this->assertInstanceOf(Carbon::class, $carbon);
        $this->assertEquals('2023-01-01 12:00:00', $carbon->format('Y-m-d H:i:s'));
        
        $carbon = Carbon::createFromFormat('d/m/Y', '15/01/2023');
        $this->assertInstanceOf(Carbon::class, $carbon);
        $this->assertEquals('2023-01-15', $carbon->format('Y-m-d'));
        
        $result = Carbon::createFromFormat('invalid', 'invalid');
        $this->assertFalse($result);
    }

    public function testParse(): void
    {
        $carbon = Carbon::parse('2023-01-01 12:00:00');
        $this->assertInstanceOf(Carbon::class, $carbon);
        $this->assertEquals('2023-01-01 12:00:00', $carbon->format('Y-m-d H:i:s'));
        
        $carbon = Carbon::parse('tomorrow');
        $this->assertInstanceOf(Carbon::class, $carbon);
    }

    public function testAddYears(): void
    {
        $carbon = Carbon::create(2023, 1, 1);
        $result = $carbon->addYears(2);
        
        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals('2025-01-01', $result->format('Y-m-d'));
    }

    public function testAddYear(): void
    {
        $carbon = Carbon::create(2023, 1, 1);
        $result = $carbon->addYear();
        
        $this->assertEquals('2024-01-01', $result->format('Y-m-d'));
    }

    public function testSubYears(): void
    {
        $carbon = Carbon::create(2023, 1, 1);
        $result = $carbon->subYears(2);
        
        $this->assertEquals('2021-01-01', $result->format('Y-m-d'));
    }

    public function testSubYear(): void
    {
        $carbon = Carbon::create(2023, 1, 1);
        $result = $carbon->subYear();
        
        $this->assertEquals('2022-01-01', $result->format('Y-m-d'));
    }

    public function testAddMonths(): void
    {
        $carbon = Carbon::create(2023, 1, 1);
        $result = $carbon->addMonths(3);
        
        $this->assertEquals('2023-04-01', $result->format('Y-m-d'));
    }

    public function testAddMonth(): void
    {
        $carbon = Carbon::create(2023, 1, 1);
        $result = $carbon->addMonth();
        
        $this->assertEquals('2023-02-01', $result->format('Y-m-d'));
    }

    public function testSubMonths(): void
    {
        $carbon = Carbon::create(2023, 4, 1);
        $result = $carbon->subMonths(2);
        
        $this->assertEquals('2023-02-01', $result->format('Y-m-d'));
    }

    public function testSubMonth(): void
    {
        $carbon = Carbon::create(2023, 2, 1);
        $result = $carbon->subMonth();
        
        $this->assertEquals('2023-01-01', $result->format('Y-m-d'));
    }

    public function testAddDays(): void
    {
        $carbon = Carbon::create(2023, 1, 1);
        $result = $carbon->addDays(10);
        
        $this->assertEquals('2023-01-11', $result->format('Y-m-d'));
    }

    public function testAddDay(): void
    {
        $carbon = Carbon::create(2023, 1, 1);
        $result = $carbon->addDay();
        
        $this->assertEquals('2023-01-02', $result->format('Y-m-d'));
    }

    public function testSubDays(): void
    {
        $carbon = Carbon::create(2023, 1, 11);
        $result = $carbon->subDays(5);
        
        $this->assertEquals('2023-01-06', $result->format('Y-m-d'));
    }

    public function testSubDay(): void
    {
        $carbon = Carbon::create(2023, 1, 2);
        $result = $carbon->subDay();
        
        $this->assertEquals('2023-01-01', $result->format('Y-m-d'));
    }

    public function testAddHours(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 12);
        $result = $carbon->addHours(6);
        
        $this->assertEquals('18:00:00', $result->format('H:i:s'));
    }

    public function testAddHour(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 12);
        $result = $carbon->addHour();
        
        $this->assertEquals('13:00:00', $result->format('H:i:s'));
    }

    public function testSubHours(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 18);
        $result = $carbon->subHours(3);
        
        $this->assertEquals('15:00:00', $result->format('H:i:s'));
    }

    public function testSubHour(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 13);
        $result = $carbon->subHour();
        
        $this->assertEquals('12:00:00', $result->format('H:i:s'));
    }

    public function testAddMinutes(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 12, 30);
        $result = $carbon->addMinutes(15);
        
        $this->assertEquals('12:45:00', $result->format('H:i:s'));
    }

    public function testAddMinute(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 12, 30);
        $result = $carbon->addMinute();
        
        $this->assertEquals('12:31:00', $result->format('H:i:s'));
    }

    public function testSubMinutes(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 12, 45);
        $result = $carbon->subMinutes(10);
        
        $this->assertEquals('12:35:00', $result->format('H:i:s'));
    }

    public function testSubMinute(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 12, 31);
        $result = $carbon->subMinute();
        
        $this->assertEquals('12:30:00', $result->format('H:i:s'));
    }

    public function testAddSeconds(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 12, 30, 30);
        $result = $carbon->addSeconds(15);
        
        $this->assertEquals('12:30:45', $result->format('H:i:s'));
    }

    public function testAddSecond(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 12, 30, 30);
        $result = $carbon->addSecond();
        
        $this->assertEquals('12:30:31', $result->format('H:i:s'));
    }

    public function testSubSeconds(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 12, 30, 45);
        $result = $carbon->subSeconds(10);
        
        $this->assertEquals('12:30:35', $result->format('H:i:s'));
    }

    public function testSubSecond(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 12, 30, 31);
        $result = $carbon->subSecond();
        
        $this->assertEquals('12:30:30', $result->format('H:i:s'));
    }

    public function testStartOfDay(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 15, 30, 45);
        $result = $carbon->startOfDay();
        
        $this->assertEquals('2023-01-01 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testEndOfDay(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 15, 30, 45);
        $result = $carbon->endOfDay();
        
        $this->assertEquals('2023-01-01 23:59:59', $result->format('Y-m-d H:i:s'));
    }

    public function testStartOfMonth(): void
    {
        $carbon = Carbon::create(2023, 6, 15, 15, 30, 45);
        $result = $carbon->startOfMonth();
        
        $this->assertEquals('2023-06-01 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testEndOfMonth(): void
    {
        $carbon = Carbon::create(2023, 6, 15, 15, 30, 45);
        $result = $carbon->endOfMonth();
        
        $this->assertEquals('2023-06-30 23:59:59', $result->format('Y-m-d H:i:s'));
    }

    public function testStartOfYear(): void
    {
        $carbon = Carbon::create(2023, 6, 15, 15, 30, 45);
        $result = $carbon->startOfYear();
        
        $this->assertEquals('2023-01-01 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testEndOfYear(): void
    {
        $carbon = Carbon::create(2023, 6, 15, 15, 30, 45);
        $result = $carbon->endOfYear();
        
        $this->assertEquals('2023-12-31 23:59:59', $result->format('Y-m-d H:i:s'));
    }

    public function testIsPast(): void
    {
        $past = Carbon::create(2020, 1, 1);
        $this->assertTrue($past->isPast());
        
        $future = Carbon::create(2030, 1, 1);
        $this->assertFalse($future->isPast());
    }

    public function testIsFuture(): void
    {
        $future = Carbon::create(2030, 1, 1);
        $this->assertTrue($future->isFuture());
        
        $past = Carbon::create(2020, 1, 1);
        $this->assertFalse($past->isFuture());
    }

    public function testIsToday(): void
    {
        $today = Carbon::today();
        $this->assertTrue($today->isToday());
        
        $yesterday = Carbon::yesterday();
        $this->assertFalse($yesterday->isToday());
        
        $tomorrow = Carbon::tomorrow();
        $this->assertFalse($tomorrow->isToday());
    }

    public function testIsTomorrow(): void
    {
        $tomorrow = Carbon::tomorrow();
        $this->assertTrue($tomorrow->isTomorrow());
        
        $today = Carbon::today();
        $this->assertFalse($today->isTomorrow());
    }

    public function testIsYesterday(): void
    {
        $yesterday = Carbon::yesterday();
        $this->assertTrue($yesterday->isYesterday());
        
        $today = Carbon::today();
        $this->assertFalse($today->isYesterday());
    }

    public function testIsWeekend(): void
    {
        // Create a Saturday
        $saturday = Carbon::create(2023, 1, 7); // 2023-01-07 is a Saturday
        $this->assertTrue($saturday->isWeekend());
        
        // Create a Sunday
        $sunday = Carbon::create(2023, 1, 8); // 2023-01-08 is a Sunday
        $this->assertTrue($sunday->isWeekend());
        
        // Create a Monday
        $monday = Carbon::create(2023, 1, 9); // 2023-01-09 is a Monday
        $this->assertFalse($monday->isWeekend());
    }

    public function testIsWeekday(): void
    {
        // Create a Monday
        $monday = Carbon::create(2023, 1, 9); // 2023-01-09 is a Monday
        $this->assertTrue($monday->isWeekday());
        
        // Create a Saturday
        $saturday = Carbon::create(2023, 1, 7); // 2023-01-07 is a Saturday
        $this->assertFalse($saturday->isWeekday());
    }

    public function testDiffInYears(): void
    {
        $carbon1 = Carbon::create(2020, 1, 1);
        $carbon2 = Carbon::create(2023, 1, 1);
        
        $this->assertEquals(3, $carbon1->diffInYears($carbon2));
        $this->assertEquals(3, $carbon2->diffInYears($carbon1));
        $this->assertEquals(-3, $carbon2->diffInYears($carbon1, false));
    }

    public function testDiffInMonths(): void
    {
        $carbon1 = Carbon::create(2023, 1, 1);
        $carbon2 = Carbon::create(2023, 4, 1);
        
        $this->assertEquals(3, $carbon1->diffInMonths($carbon2));
        $this->assertEquals(3, $carbon2->diffInMonths($carbon1));
    }

    public function testDiffInDays(): void
    {
        $carbon1 = Carbon::create(2023, 1, 1);
        $carbon2 = Carbon::create(2023, 1, 11);
        
        $this->assertEquals(10, $carbon1->diffInDays($carbon2));
        $this->assertEquals(10, $carbon2->diffInDays($carbon1));
    }

    public function testDiffInHours(): void
    {
        $carbon1 = Carbon::create(2023, 1, 1, 12);
        $carbon2 = Carbon::create(2023, 1, 1, 18);
        
        $this->assertEquals(6, $carbon1->diffInHours($carbon2));
        $this->assertEquals(6, $carbon2->diffInHours($carbon1));
    }

    public function testDiffInMinutes(): void
    {
        $carbon1 = Carbon::create(2023, 1, 1, 12, 30);
        $carbon2 = Carbon::create(2023, 1, 1, 12, 45);
        
        $this->assertEquals(15, $carbon1->diffInMinutes($carbon2));
        $this->assertEquals(15, $carbon2->diffInMinutes($carbon1));
    }

    public function testDiffInSeconds(): void
    {
        $carbon1 = Carbon::create(2023, 1, 1, 12, 30, 30);
        $carbon2 = Carbon::create(2023, 1, 1, 12, 30, 45);
        
        $this->assertEquals(15, $carbon1->diffInSeconds($carbon2));
        $this->assertEquals(15, $carbon2->diffInSeconds($carbon1));
        $this->assertEquals(15, $carbon2->diffInSeconds($carbon1, false));
    }

    public function testDiffForHumans(): void
    {
        $now = Carbon::now();
        $past = $now->copy()->subHours(2);
        $future = $now->copy()->addHours(3);
        
        $this->assertStringContainsString('ago', $past->diffForHumans());
        $this->assertStringContainsString('in', $future->diffForHumans());
        
        $this->assertStringContainsString('before', $past->diffForHumans($now));
        $this->assertStringContainsString('after', $future->diffForHumans($now));
        
        $absolute = $past->diffForHumans($now, true);
        $this->assertStringNotContainsString('ago', $absolute);
        $this->assertStringNotContainsString('before', $absolute);
    }

    public function testToString(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 12, 30, 45);
        $this->assertEquals('2023-01-01 12:30:45', (string) $carbon);
        $this->assertEquals('2023-01-01 12:30:45', $carbon->__toString());
    }

    public function testToArray(): void
    {
        $carbon = Carbon::create(2023, 6, 15, 14, 30, 45);
        $array = $carbon->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals(2023, $array['year']);
        $this->assertEquals(6, $array['month']);
        $this->assertEquals(15, $array['day']);
        $this->assertEquals(14, $array['hour']);
        $this->assertEquals(30, $array['minute']);
        $this->assertEquals(45, $array['second']);
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertArrayHasKey('timezone', $array);
    }

    public function testToJson(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 12, 30, 45);
        $json = $carbon->toJson();
        
        $this->assertIsString($json);
        $this->assertJson($json);
    }

    public function testAge(): void
    {
        $birthDate = Carbon::create(2000, 1, 1);
        $now = Carbon::create(2023, 1, 1);
        
        $this->assertEquals(23, $birthDate->age($now));
    }

    public function testCopy(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 12, 30, 45);
        $copy = $carbon->copy();
        
        $this->assertInstanceOf(Carbon::class, $copy);
        $this->assertEquals($carbon->format('Y-m-d H:i:s'), $copy->format('Y-m-d H:i:s'));
        $this->assertNotSame($carbon, $copy);
    }

    public function testClone(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 12, 30, 45);
        $clone = $carbon->clone();
        
        $this->assertInstanceOf(Carbon::class, $clone);
        $this->assertEquals($carbon->format('Y-m-d H:i:s'), $clone->format('Y-m-d H:i:s'));
        $this->assertNotSame($carbon, $clone);
    }

    public function testConstants(): void
    {
        $this->assertEquals('Y-m-d H:i:s', Carbon::DEFAULT_TO_STRING_FORMAT);
        $this->assertEquals(0, Carbon::SUNDAY);
        $this->assertEquals(1, Carbon::MONDAY);
        $this->assertEquals(6, Carbon::SATURDAY);
        $this->assertEquals(1, Carbon::JANUARY);
        $this->assertEquals(12, Carbon::DECEMBER);
    }

    public function testFormatConstants(): void
    {
        $carbon = Carbon::create(2023, 1, 1, 12, 30, 45);
        
        // Test inherited constants from DateTimeInterface
        $this->assertIsString($carbon->format(Carbon::ATOM));
        $this->assertIsString($carbon->format(Carbon::ISO8601));
        $this->assertIsString($carbon->format(Carbon::RFC3339));
        $this->assertIsString($carbon->format(Carbon::W3C));
    }
}