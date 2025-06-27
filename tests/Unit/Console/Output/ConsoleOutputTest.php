<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Output;

use Tests\TestCase;
use LengthOfRope\TreeHouse\Console\Output\ConsoleOutput;
use LengthOfRope\TreeHouse\Console\Output\OutputFormatter;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;

/**
 * Tests for Console Output
 */
class ConsoleOutputTest extends TestCase
{
    private ConsoleOutput $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->output = new ConsoleOutput();
    }

    public function testImplementsOutputInterface(): void
    {
        $this->assertInstanceOf(OutputInterface::class, $this->output);
    }

    public function testVerbosityConstants(): void
    {
        $this->assertEquals(16, ConsoleOutput::VERBOSITY_QUIET);
        $this->assertEquals(32, ConsoleOutput::VERBOSITY_NORMAL);
        $this->assertEquals(64, ConsoleOutput::VERBOSITY_VERBOSE);
        $this->assertEquals(128, ConsoleOutput::VERBOSITY_VERY_VERBOSE);
        $this->assertEquals(256, ConsoleOutput::VERBOSITY_DEBUG);
    }

    public function testDefaultVerbosity(): void
    {
        $this->assertEquals(ConsoleOutput::VERBOSITY_NORMAL, $this->output->getVerbosity());
        $this->assertFalse($this->output->isQuiet());
        $this->assertFalse($this->output->isVerbose());
        $this->assertFalse($this->output->isVeryVerbose());
        $this->assertFalse($this->output->isDebug());
    }

    public function testSetVerbosityQuiet(): void
    {
        $this->output->setVerbosity(ConsoleOutput::VERBOSITY_QUIET);
        
        $this->assertEquals(ConsoleOutput::VERBOSITY_QUIET, $this->output->getVerbosity());
        $this->assertTrue($this->output->isQuiet());
        $this->assertFalse($this->output->isVerbose());
        $this->assertFalse($this->output->isVeryVerbose());
        $this->assertFalse($this->output->isDebug());
    }

    public function testSetVerbosityVerbose(): void
    {
        $this->output->setVerbosity(ConsoleOutput::VERBOSITY_VERBOSE);
        
        $this->assertEquals(ConsoleOutput::VERBOSITY_VERBOSE, $this->output->getVerbosity());
        $this->assertFalse($this->output->isQuiet());
        $this->assertTrue($this->output->isVerbose());
        $this->assertFalse($this->output->isVeryVerbose());
        $this->assertFalse($this->output->isDebug());
    }

    public function testSetVerbosityVeryVerbose(): void
    {
        $this->output->setVerbosity(ConsoleOutput::VERBOSITY_VERY_VERBOSE);
        
        $this->assertEquals(ConsoleOutput::VERBOSITY_VERY_VERBOSE, $this->output->getVerbosity());
        $this->assertFalse($this->output->isQuiet());
        $this->assertTrue($this->output->isVerbose());
        $this->assertTrue($this->output->isVeryVerbose());
        $this->assertFalse($this->output->isDebug());
    }

    public function testSetVerbosityDebug(): void
    {
        $this->output->setVerbosity(ConsoleOutput::VERBOSITY_DEBUG);
        
        $this->assertEquals(ConsoleOutput::VERBOSITY_DEBUG, $this->output->getVerbosity());
        $this->assertFalse($this->output->isQuiet());
        $this->assertTrue($this->output->isVerbose());
        $this->assertTrue($this->output->isVeryVerbose());
        $this->assertTrue($this->output->isDebug());
    }

    public function testWriteOutput(): void
    {
        ob_start();
        $this->output->write('Test message');
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Test message', $output);
    }

    public function testWritelnOutput(): void
    {
        ob_start();
        $this->output->writeln('Test message');
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Test message', $output);
        $this->assertStringEndsWith(PHP_EOL, $output);
    }

    public function testQuietModeSupressesOutput(): void
    {
        $this->output->setVerbosity(ConsoleOutput::VERBOSITY_QUIET);
        
        ob_start();
        $this->output->write('This should not appear');
        $this->output->writeln('Neither should this');
        $output = ob_get_clean();
        
        $this->assertEmpty($output);
    }

    public function testFormatterIntegration(): void
    {
        $formatter = $this->output->getFormatter();
        $this->assertInstanceOf(OutputFormatter::class, $formatter);
        
        $newFormatter = new OutputFormatter();
        $this->output->setFormatter($newFormatter);
        $this->assertSame($newFormatter, $this->output->getFormatter());
    }

    public function testFormattedOutput(): void
    {
        ob_start();
        $this->output->write('<info>Formatted message</info>');
        $output = ob_get_clean();
        
        // The exact output depends on whether terminal supports colors
        // but the message should be present
        $this->assertStringContainsString('Formatted message', $output);
    }
}

/**
 * Tests for Output Formatter
 */
class OutputFormatterTest extends TestCase
{
    private OutputFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new OutputFormatter();
    }

    public function testFormatWithoutDecorationStripsTagsonly(): void
    {
        $this->formatter->setDecorated(false);
        
        $formatted = $this->formatter->format('<info>Test message</info>');
        $this->assertEquals('Test message', $formatted);
    }

    public function testFormatPredefinedTags(): void
    {
        $this->formatter->setDecorated(true);
        
        $tags = ['info', 'success', 'comment', 'question', 'error', 'warning'];
        
        foreach ($tags as $tag) {
            $formatted = $this->formatter->format("<{$tag}>Test</{$tag}>");
            // Should contain ANSI codes when decorated
            $this->assertStringContainsString('Test', $formatted);
        }
    }

    public function testFormatClosingTagResetsFormatting(): void
    {
        $this->formatter->setDecorated(true);
        
        $formatted = $this->formatter->format('<info>Test</info>');
        // Should end with reset code
        $this->assertStringContainsString("\033[0m", $formatted);
    }

    public function testFormatCustomColorAttributes(): void
    {
        $this->formatter->setDecorated(true);
        
        $formatted = $this->formatter->format('<color=red>Red text</color>');
        $this->assertStringContainsString('Red text', $formatted);
    }

    public function testFormatCustomBackgroundAttributes(): void
    {
        $this->formatter->setDecorated(true);
        
        $formatted = $this->formatter->format('<bg=blue>Blue background</bg>');
        $this->assertStringContainsString('Blue background', $formatted);
    }

    public function testFormatCustomStyleAttributes(): void
    {
        $this->formatter->setDecorated(true);
        
        $formatted = $this->formatter->format('<style=bold>Bold text</style>');
        $this->assertStringContainsString('Bold text', $formatted);
    }

    public function testFormatMultipleAttributes(): void
    {
        $this->formatter->setDecorated(true);
        
        $formatted = $this->formatter->format('<color=red bg=yellow style=bold>Styled text</color>');
        $this->assertStringContainsString('Styled text', $formatted);
    }

    public function testStripTagsWithComplexContent(): void
    {
        $this->formatter->setDecorated(false);
        
        $formatted = $this->formatter->format('<info>Info</info> and <error>Error</error>');
        $this->assertEquals('Info and Error', $formatted);
    }

    public function testFormatNestedTags(): void
    {
        $this->formatter->setDecorated(true);
        
        $formatted = $this->formatter->format('<info>Info <error>nested error</error> back to info</info>');
        $this->assertStringContainsString('Info', $formatted);
        $this->assertStringContainsString('nested error', $formatted);
        $this->assertStringContainsString('back to info', $formatted);
    }

    public function testSetAndGetDecorated(): void
    {
        $this->formatter->setDecorated(true);
        $this->assertTrue($this->formatter->isDecorated());
        
        $this->formatter->setDecorated(false);
        $this->assertFalse($this->formatter->isDecorated());
    }

    public function testFormatWithInvalidTag(): void
    {
        $this->formatter->setDecorated(true);
        
        $formatted = $this->formatter->format('<invalid>Test</invalid>');
        $this->assertStringContainsString('Test', $formatted);
    }

    public function testFormatWithSelfClosingTags(): void
    {
        $this->formatter->setDecorated(false);
        
        $formatted = $this->formatter->format('Before <br/> After');
        $this->assertEquals('Before  After', $formatted);
    }

    public function testFormatPreservesUntaggedContent(): void
    {
        $formatted = $this->formatter->format('Plain text without tags');
        $this->assertEquals('Plain text without tags', $formatted);
    }

    public function testFormatWithSpecialCharacters(): void
    {
        $this->formatter->setDecorated(false);
        
        $formatted = $this->formatter->format('<info>Special chars: !@#$%^&*()_+-=[]{}|;:,.<>?</info>');
        $this->assertEquals('Special chars: !@#$%^&*()_+-=[]{}|;:,.<>?', $formatted);
    }

    public function testFormatWithUnicodeCharacters(): void
    {
        $this->formatter->setDecorated(false);
        
        $formatted = $this->formatter->format('<info>Unicode: ñáéíóú ñäëïöü 中文 🌟</info>');
        $this->assertEquals('Unicode: ñáéíóú ñäëïöü 中文 🌟', $formatted);
    }

    public function testFormatWithEmptyTag(): void
    {
        $this->formatter->setDecorated(false);
        
        $formatted = $this->formatter->format('<info></info>');
        $this->assertEquals('', $formatted);
    }

    public function testFormatWithOnlyOpeningTag(): void
    {
        $this->formatter->setDecorated(false);
        
        $formatted = $this->formatter->format('<info>No closing tag');
        $this->assertEquals('No closing tag', $formatted);
    }
}