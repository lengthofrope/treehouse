<?php

declare(strict_types=1);

namespace Tests\Unit\Errors\Classification;

use LengthOfRope\TreeHouse\Errors\Classification\ClassificationResult;
use LengthOfRope\TreeHouse\Errors\Classification\ExceptionClassifier;
use PHPUnit\Framework\TestCase;

class ClassificationResultTest extends TestCase
{
    public function testCanCreateWithAllProperties(): void
    {
        $result = new ClassificationResult(
            category: 'database',
            severity: 'critical',
            shouldReport: true,
            logLevel: 'error',
            isSecurity: false,
            isCritical: true,
            tags: ['db', 'connection'],
            metadata: ['host' => 'localhost']
        );

        $this->assertEquals('database', $result->category);
        $this->assertEquals('critical', $result->severity);
        $this->assertTrue($result->shouldReport);
        $this->assertEquals('error', $result->logLevel);
        $this->assertFalse($result->isSecurity);
        $this->assertTrue($result->isCritical);
        $this->assertEquals(['db', 'connection'], $result->tags);
        $this->assertEquals(['host' => 'localhost'], $result->metadata);
    }

    public function testToArrayReturnsAllProperties(): void
    {
        $result = new ClassificationResult(
            category: 'authentication',
            severity: 'medium',
            shouldReport: false,
            logLevel: 'warning',
            isSecurity: true,
            isCritical: false,
            tags: ['auth', 'login'],
            metadata: ['user' => 'test']
        );

        $array = $result->toArray();

        $expected = [
            'category' => 'authentication',
            'severity' => 'medium',
            'should_report' => false,
            'log_level' => 'warning',
            'is_security' => true,
            'is_critical' => false,
            'tags' => ['auth', 'login'],
            'metadata' => ['user' => 'test']
        ];

        $this->assertEquals($expected, $array);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $result = new ClassificationResult(
            category: 'validation',
            severity: 'low',
            shouldReport: false,
            logLevel: 'info',
            isSecurity: false,
            isCritical: false,
            tags: ['validation'],
            metadata: ['field' => 'email']
        );

        $json = $result->toJson();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertEquals('validation', $decoded['category']);
        $this->assertEquals('low', $decoded['severity']);
        $this->assertFalse($decoded['should_report']);
    }

    public function testIsHighPriorityWithCriticalSeverity(): void
    {
        $result = new ClassificationResult(
            category: 'system',
            severity: 'critical',
            shouldReport: true,
            logLevel: 'critical',
            isSecurity: false,
            isCritical: false,
            tags: [],
            metadata: []
        );

        $this->assertTrue($result->isHighPriority());
    }

    public function testIsHighPriorityWithHighSeverity(): void
    {
        $result = new ClassificationResult(
            category: 'system',
            severity: 'high',
            shouldReport: true,
            logLevel: 'error',
            isSecurity: false,
            isCritical: false,
            tags: [],
            metadata: []
        );

        $this->assertTrue($result->isHighPriority());
    }

    public function testIsHighPriorityWithSecurityFlag(): void
    {
        $result = new ClassificationResult(
            category: 'security',
            severity: 'medium',
            shouldReport: true,
            logLevel: 'warning',
            isSecurity: true,
            isCritical: false,
            tags: [],
            metadata: []
        );

        $this->assertTrue($result->isHighPriority());
    }

    public function testIsHighPriorityWithCriticalFlag(): void
    {
        $result = new ClassificationResult(
            category: 'general',
            severity: 'low',
            shouldReport: false,
            logLevel: 'info',
            isSecurity: false,
            isCritical: true,
            tags: [],
            metadata: []
        );

        $this->assertTrue($result->isHighPriority());
    }

    public function testIsNotHighPriorityForLowSeverity(): void
    {
        $result = new ClassificationResult(
            category: 'validation',
            severity: 'low',
            shouldReport: false,
            logLevel: 'info',
            isSecurity: false,
            isCritical: false,
            tags: [],
            metadata: []
        );

        $this->assertFalse($result->isHighPriority());
    }

    public function testShouldEscalateWithCriticalFlag(): void
    {
        $result = new ClassificationResult(
            category: 'system',
            severity: 'medium',
            shouldReport: true,
            logLevel: 'warning',
            isSecurity: false,
            isCritical: true,
            tags: [],
            metadata: []
        );

        $this->assertTrue($result->shouldEscalate());
    }

    public function testShouldEscalateWithSecurityAndHighSeverity(): void
    {
        $result = new ClassificationResult(
            category: 'security',
            severity: 'high',
            shouldReport: true,
            logLevel: 'error',
            isSecurity: true,
            isCritical: false,
            tags: [],
            metadata: []
        );

        $this->assertTrue($result->shouldEscalate());
    }

    public function testShouldNotEscalateWithSecurityAndLowSeverity(): void
    {
        $result = new ClassificationResult(
            category: 'security',
            severity: 'low',
            shouldReport: false,
            logLevel: 'info',
            isSecurity: true,
            isCritical: false,
            tags: [],
            metadata: []
        );

        $this->assertFalse($result->shouldEscalate());
    }

    public function testGetSummaryBasic(): void
    {
        $result = new ClassificationResult(
            category: 'database',
            severity: 'critical',
            shouldReport: true,
            logLevel: 'critical',
            isSecurity: false,
            isCritical: false,
            tags: [],
            metadata: []
        );

        $summary = $result->getSummary();
        $this->assertEquals('Critical database exception', $summary);
    }

    public function testGetSummaryWithSecurityFlag(): void
    {
        $result = new ClassificationResult(
            category: 'authentication',
            severity: 'high',
            shouldReport: true,
            logLevel: 'error',
            isSecurity: true,
            isCritical: false,
            tags: [],
            metadata: []
        );

        $summary = $result->getSummary();
        $this->assertEquals('High authentication exception (security)', $summary);
    }

    public function testGetSummaryWithCriticalFlag(): void
    {
        $result = new ClassificationResult(
            category: 'system',
            severity: 'medium',
            shouldReport: true,
            logLevel: 'warning',
            isSecurity: false,
            isCritical: true,
            tags: [],
            metadata: []
        );

        $summary = $result->getSummary();
        $this->assertEquals('Medium system exception (critical)', $summary);
    }

    public function testGetSummaryWithBothFlags(): void
    {
        $result = new ClassificationResult(
            category: 'security',
            severity: 'critical',
            shouldReport: true,
            logLevel: 'critical',
            isSecurity: true,
            isCritical: true,
            tags: [],
            metadata: []
        );

        $summary = $result->getSummary();
        $this->assertEquals('Critical security exception (security) (critical)', $summary);
    }

    public function testHasTag(): void
    {
        $result = new ClassificationResult(
            category: 'database',
            severity: 'critical',
            shouldReport: true,
            logLevel: 'critical',
            isSecurity: false,
            isCritical: false,
            tags: ['db', 'connection', 'timeout'],
            metadata: []
        );

        $this->assertTrue($result->hasTag('db'));
        $this->assertTrue($result->hasTag('connection'));
        $this->assertTrue($result->hasTag('timeout'));
        $this->assertFalse($result->hasTag('auth'));
        $this->assertFalse($result->hasTag('validation'));
    }

    public function testGetTagsByPrefix(): void
    {
        $result = new ClassificationResult(
            category: 'system',
            severity: 'high',
            shouldReport: true,
            logLevel: 'error',
            isSecurity: false,
            isCritical: false,
            tags: ['db:connection', 'db:timeout', 'auth:failed', 'system:memory'],
            metadata: []
        );

        $dbTags = $result->getTagsByPrefix('db');
        $this->assertEquals(['db:connection', 'db:timeout'], $dbTags);

        $authTags = $result->getTagsByPrefix('auth');
        $this->assertEquals(['auth:failed'], $authTags);

        $systemTags = $result->getTagsByPrefix('system');
        $this->assertEquals(['system:memory'], $systemTags);

        $nonExistentTags = $result->getTagsByPrefix('validation');
        $this->assertEquals([], $nonExistentTags);
    }

    public function testGetMetadata(): void
    {
        $metadata = [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test_db',
            'user' => 'test_user'
        ];

        $result = new ClassificationResult(
            category: 'database',
            severity: 'critical',
            shouldReport: true,
            logLevel: 'critical',
            isSecurity: false,
            isCritical: false,
            tags: [],
            metadata: $metadata
        );

        $this->assertEquals('localhost', $result->getMetadata('host'));
        $this->assertEquals(3306, $result->getMetadata('port'));
        $this->assertEquals('test_db', $result->getMetadata('database'));
        $this->assertEquals('test_user', $result->getMetadata('user'));
    }

    public function testGetMetadataWithDefault(): void
    {
        $result = new ClassificationResult(
            category: 'database',
            severity: 'critical',
            shouldReport: true,
            logLevel: 'critical',
            isSecurity: false,
            isCritical: false,
            tags: [],
            metadata: ['host' => 'localhost']
        );

        $this->assertEquals('localhost', $result->getMetadata('host'));
        $this->assertNull($result->getMetadata('port'));
        $this->assertEquals(3306, $result->getMetadata('port', 3306));
        $this->assertEquals('default', $result->getMetadata('nonexistent', 'default'));
    }
}