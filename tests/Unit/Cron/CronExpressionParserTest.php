<?php

declare(strict_types=1);

namespace Tests\Unit\Cron;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Cron\CronExpressionParser;
use LengthOfRope\TreeHouse\Cron\Exceptions\CronException;

/**
 * Test suite for CronExpressionParser
 */
class CronExpressionParserTest extends TestCase
{
    public function testBasicCronExpressions(): void
    {
        // Every minute
        $parser = new CronExpressionParser('* * * * *');
        $this->assertTrue($parser->isDue(time()));
        
        // Every hour at minute 0
        $parser = new CronExpressionParser('0 * * * *');
        $testTime = strtotime('2024-01-01 12:00:00');
        $this->assertTrue($parser->isDue($testTime));
        
        $testTime = strtotime('2024-01-01 12:01:00');
        $this->assertFalse($parser->isDue($testTime));
    }

    public function testSpecificTimeExpressions(): void
    {
        // Daily at 2 AM
        $parser = new CronExpressionParser('0 2 * * *');
        $testTime = strtotime('2024-01-01 02:00:00');
        $this->assertTrue($parser->isDue($testTime));
        
        $testTime = strtotime('2024-01-01 03:00:00');
        $this->assertFalse($parser->isDue($testTime));
    }

    public function testWeeklyExpressions(): void
    {
        // Weekly on Sunday at midnight
        $parser = new CronExpressionParser('0 0 * * 0');
        $testTime = strtotime('Sunday 2024-01-07 00:00:00');
        $this->assertTrue($parser->isDue($testTime));
        
        $testTime = strtotime('Monday 2024-01-08 00:00:00');
        $this->assertFalse($parser->isDue($testTime));
    }

    public function testRangeExpressions(): void
    {
        // Business hours: 9 AM to 5 PM
        $parser = new CronExpressionParser('0 9-17 * * *');
        $testTime = strtotime('2024-01-01 10:00:00');
        $this->assertTrue($parser->isDue($testTime));
        
        $testTime = strtotime('2024-01-01 18:00:00');
        $this->assertFalse($parser->isDue($testTime));
    }

    public function testStepExpressions(): void
    {
        // Every 15 minutes
        $parser = new CronExpressionParser('*/15 * * * *');
        $testTime = strtotime('2024-01-01 12:00:00');
        $this->assertTrue($parser->isDue($testTime));
        
        $testTime = strtotime('2024-01-01 12:15:00');
        $this->assertTrue($parser->isDue($testTime));
        
        $testTime = strtotime('2024-01-01 12:07:00');
        $this->assertFalse($parser->isDue($testTime));
    }

    public function testListExpressions(): void
    {
        // At 8 AM and 6 PM
        $parser = new CronExpressionParser('0 8,18 * * *');
        $testTime = strtotime('2024-01-01 08:00:00');
        $this->assertTrue($parser->isDue($testTime));
        
        $testTime = strtotime('2024-01-01 18:00:00');
        $this->assertTrue($parser->isDue($testTime));
        
        $testTime = strtotime('2024-01-01 12:00:00');
        $this->assertFalse($parser->isDue($testTime));
    }

    public function testNamedValues(): void
    {
        // January and December
        $parser = new CronExpressionParser('0 0 1 jan,dec *');
        $testTime = strtotime('2024-01-01 00:00:00');
        $this->assertTrue($parser->isDue($testTime));
        
        $testTime = strtotime('2024-12-01 00:00:00');
        $this->assertTrue($parser->isDue($testTime));
        
        $testTime = strtotime('2024-06-01 00:00:00');
        $this->assertFalse($parser->isDue($testTime));
    }

    public function testWeekdayNames(): void
    {
        // Monday to Friday
        $parser = new CronExpressionParser('0 9 * * mon-fri');
        $testTime = strtotime('Monday 2024-01-01 09:00:00');
        $this->assertTrue($parser->isDue($testTime));
        
        $testTime = strtotime('Saturday 2024-01-06 09:00:00');
        $this->assertFalse($parser->isDue($testTime));
    }

    public function testSundayHandling(): void
    {
        // Sunday can be 0 or 7
        $parser0 = new CronExpressionParser('0 0 * * 0');
        $parser7 = new CronExpressionParser('0 0 * * 7');
        
        $sundayTime = strtotime('Sunday 2024-01-07 00:00:00');
        $this->assertTrue($parser0->isDue($sundayTime));
        $this->assertTrue($parser7->isDue($sundayTime));
    }

    public function testNextRunTime(): void
    {
        // Daily at 2 AM
        $parser = new CronExpressionParser('0 2 * * *');
        $currentTime = strtotime('2024-01-01 01:00:00');
        $nextRun = $parser->getNextRunTime($currentTime);
        
        $expectedNext = strtotime('2024-01-01 02:00:00');
        $this->assertEquals($expectedNext, $nextRun);
    }

    public function testPreviousRunTime(): void
    {
        // Daily at 2 AM
        $parser = new CronExpressionParser('0 2 * * *');
        $currentTime = strtotime('2024-01-01 03:00:00');
        $previousRun = $parser->getPreviousRunTime($currentTime);
        
        $expectedPrevious = strtotime('2024-01-01 02:00:00');
        $this->assertEquals($expectedPrevious, $previousRun);
    }

    public function testUpcomingRunTimes(): void
    {
        // Every hour
        $parser = new CronExpressionParser('0 * * * *');
        $currentTime = strtotime('2024-01-01 12:30:00');
        $upcoming = $parser->getUpcomingRunTimes(3, $currentTime);
        
        $this->assertCount(3, $upcoming);
        $this->assertEquals(strtotime('2024-01-01 13:00:00'), $upcoming[0]);
        $this->assertEquals(strtotime('2024-01-01 14:00:00'), $upcoming[1]);
        $this->assertEquals(strtotime('2024-01-01 15:00:00'), $upcoming[2]);
    }

    public function testDescriptions(): void
    {
        $parser = new CronExpressionParser('* * * * *');
        $this->assertEquals('Every minute', $parser->getDescription());
        
        $parser = new CronExpressionParser('0 * * * *');
        $this->assertEquals('Every hour', $parser->getDescription());
        
        $parser = new CronExpressionParser('0 0 * * *');
        $this->assertEquals('Daily at midnight', $parser->getDescription());
        
        $parser = new CronExpressionParser('0 0 * * 0');
        $this->assertEquals('Weekly on Sunday at midnight', $parser->getDescription());
        
        $parser = new CronExpressionParser('0 0 1 * *');
        $this->assertEquals('Monthly on the 1st at midnight', $parser->getDescription());
    }

    public function testInvalidExpressions(): void
    {
        $this->expectException(CronException::class);
        new CronExpressionParser('* * * *'); // Too few fields
    }

    public function testInvalidFieldValues(): void
    {
        $this->expectException(CronException::class);
        new CronExpressionParser('60 * * * *'); // Invalid minute
    }

    public function testInvalidRange(): void
    {
        $this->expectException(CronException::class);
        new CronExpressionParser('10-5 * * * *'); // Invalid range (start > end)
    }

    public function testInvalidStepValue(): void
    {
        $this->expectException(CronException::class);
        new CronExpressionParser('*/0 * * * *'); // Invalid step (zero)
    }

    public function testInvalidNamedValue(): void
    {
        $this->expectException(CronException::class);
        new CronExpressionParser('0 0 * invalid *'); // Invalid month name
    }

    public function testGetParsedExpression(): void
    {
        $parser = new CronExpressionParser('0,30 9-17 * * 1-5');
        $parsed = $parser->getParsedExpression();
        
        $this->assertEquals([0, 30], $parsed['minute']);
        $this->assertEquals([9, 10, 11, 12, 13, 14, 15, 16, 17], $parsed['hour']);
        $this->assertEquals(range(1, 31), $parsed['day']);
        $this->assertEquals(range(1, 12), $parsed['month']);
        $this->assertEquals([1, 2, 3, 4, 5], $parsed['weekday']);
    }

    public function testGetExpression(): void
    {
        $expression = '0 2 * * *';
        $parser = new CronExpressionParser($expression);
        $this->assertEquals($expression, $parser->getExpression());
    }

    public function testComplexExpression(): void
    {
        // Every 10 minutes between 9 AM and 5 PM on weekdays
        $parser = new CronExpressionParser('*/10 9-17 * * 1-5');
        
        // Should match
        $testTime = strtotime('Monday 2024-01-01 09:10:00');
        $this->assertTrue($parser->isDue($testTime));
        
        $testTime = strtotime('Friday 2024-01-05 17:00:00');
        $this->assertTrue($parser->isDue($testTime));
        
        // Should not match (weekend)
        $testTime = strtotime('Saturday 2024-01-06 09:10:00');
        $this->assertFalse($parser->isDue($testTime));
        
        // Should not match (outside hours)
        $testTime = strtotime('Monday 2024-01-01 18:10:00');
        $this->assertFalse($parser->isDue($testTime));
        
        // Should not match (wrong minute)
        $testTime = strtotime('Monday 2024-01-01 09:05:00');
        $this->assertFalse($parser->isDue($testTime));
    }
}