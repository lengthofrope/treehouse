<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Input;

use Tests\TestCase;
use LengthOfRope\TreeHouse\Console\Input\InputParser;
use LengthOfRope\TreeHouse\Console\Input\ParsedInput;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;

/**
 * Tests for Input Parser
 */
class InputParserTest extends TestCase
{
    private InputParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new InputParser();
    }

    public function testParseBasicCommand(): void
    {
        $argv = ['script.php', 'test:command'];
        $input = $this->parser->parse($argv);
        
        $this->assertInstanceOf(ParsedInput::class, $input);
        $this->assertEquals('test:command', $input->getArgument('command'));
        $this->assertTrue($input->hasArgument('command'));
        $this->assertEquals(['test:command'], $input->getRawArguments());
    }

    public function testParseCommandWithArguments(): void
    {
        $argv = ['script.php', 'test:command', 'arg1', 'arg2', 'arg3'];
        $input = $this->parser->parse($argv);
        
        $this->assertEquals('test:command', $input->getArgument('command'));
        $this->assertEquals('arg1', $input->getArgument('arg1'));
        $this->assertEquals('arg2', $input->getArgument('arg2'));
        $this->assertEquals('arg3', $input->getArgument('arg3'));
    }

    public function testParseLongOptionsWithEquals(): void
    {
        $argv = ['script.php', 'command', '--format=json', '--verbose=true'];
        $input = $this->parser->parse($argv);
        
        $this->assertEquals('json', $input->getOption('format'));
        $this->assertEquals('true', $input->getOption('verbose'));
        $this->assertTrue($input->hasOption('format'));
        $this->assertTrue($input->hasOption('verbose'));
    }

    public function testParseLongOptionsWithSpaces(): void
    {
        $argv = ['script.php', 'command', '--format', 'json', '--output', 'file.txt'];
        $input = $this->parser->parse($argv);
        
        $this->assertEquals('json', $input->getOption('format'));
        $this->assertEquals('file.txt', $input->getOption('output'));
    }

    public function testParseLongOptionFlags(): void
    {
        $argv = ['script.php', 'command', '--verbose', '--debug'];
        $input = $this->parser->parse($argv);
        
        $this->assertTrue($input->getOption('verbose'));
        $this->assertTrue($input->getOption('debug'));
        $this->assertTrue($input->hasOption('verbose'));
        $this->assertTrue($input->hasOption('debug'));
    }

    public function testParseShortOptionsWithValues(): void
    {
        $argv = ['script.php', 'command', '-f', 'json', '-o', 'output.txt'];
        $input = $this->parser->parse($argv);
        
        $this->assertEquals('json', $input->getOption('f'));
        $this->assertEquals('output.txt', $input->getOption('o'));
    }

    public function testParseShortOptionFlags(): void
    {
        $argv = ['script.php', 'command', '-v', '-d'];
        $input = $this->parser->parse($argv);
        
        $this->assertTrue($input->getOption('v'));
        $this->assertTrue($input->getOption('d'));
    }

    public function testParseMultipleShortFlags(): void
    {
        $argv = ['script.php', 'command', '-vdf'];
        $input = $this->parser->parse($argv);
        
        $this->assertTrue($input->getOption('v'));
        $this->assertTrue($input->getOption('d'));
        $this->assertTrue($input->getOption('f'));
    }

    public function testParseShortOptionWithAttachedValue(): void
    {
        $argv = ['script.php', 'command', '-fjson', '-ooutput.txt'];
        $input = $this->parser->parse($argv);
        
        $this->assertEquals('json', $input->getOption('f'));
        $this->assertEquals('output.txt', $input->getOption('o'));
    }

    public function testParseMixedArgumentsAndOptions(): void
    {
        $argv = ['script.php', 'migrate:run', 'database', '--force', '--rollback=5', 'batch'];
        $input = $this->parser->parse($argv);
        
        // Arguments
        $this->assertEquals('migrate:run', $input->getArgument('command'));
        $this->assertEquals('database', $input->getArgument('arg1'));
        $this->assertEquals('batch', $input->getArgument('arg2'));
        
        // Options
        $this->assertTrue($input->getOption('force'));
        $this->assertEquals('5', $input->getOption('rollback'));
    }

    public function testParseComplexRealWorldExample(): void
    {
        $argv = [
            'script.php', 
            'cache:clear', 
            '--store=redis', 
            '-v', 
            '--tags=user,session', 
            'prefix',
            '--dry-run'
        ];
        $input = $this->parser->parse($argv);
        
        // Command and arguments
        $this->assertEquals('cache:clear', $input->getArgument('command'));
        $this->assertEquals('prefix', $input->getArgument('arg1'));
        
        // Options
        $this->assertEquals('redis', $input->getOption('store'));
        $this->assertEquals('user,session', $input->getOption('tags'));
        $this->assertTrue($input->getOption('v'));
        $this->assertTrue($input->getOption('dry-run'));
    }

    public function testParseEmptyArguments(): void
    {
        $argv = ['script.php'];
        $input = $this->parser->parse($argv);
        
        $this->assertNull($input->getArgument('command'));
        $this->assertFalse($input->hasArgument('command'));
        $this->assertEmpty($input->getOptions());
    }

    public function testParseOnlyOptions(): void
    {
        $argv = ['script.php', '--help', '-v'];
        $input = $this->parser->parse($argv);
        
        $this->assertNull($input->getArgument('command'));
        $this->assertTrue($input->getOption('help'));
        $this->assertTrue($input->getOption('v'));
    }

    public function testGetAllArguments(): void
    {
        $argv = ['script.php', 'command', 'arg1', 'arg2'];
        $input = $this->parser->parse($argv);
        
        $arguments = $input->getArguments();
        $this->assertEquals([
            'command' => 'command',
            'arg1' => 'arg1',
            'arg2' => 'arg2'
        ], $arguments);
    }

    public function testGetAllOptions(): void
    {
        $argv = ['script.php', 'command', '--format=json', '-v', '--debug'];
        $input = $this->parser->parse($argv);
        
        $options = $input->getOptions();
        $this->assertEquals([
            'format' => 'json',
            'v' => true,
            'debug' => true
        ], $options);
    }

    public function testGetNonExistentArgument(): void
    {
        $argv = ['script.php', 'command'];
        $input = $this->parser->parse($argv);
        
        $this->assertNull($input->getArgument('nonexistent'));
        $this->assertFalse($input->hasArgument('nonexistent'));
    }

    public function testGetNonExistentOption(): void
    {
        $argv = ['script.php', 'command', '--existing=value'];
        $input = $this->parser->parse($argv);
        
        $this->assertNull($input->getOption('nonexistent'));
        $this->assertFalse($input->hasOption('nonexistent'));
    }

    public function testRawArgumentsPreservation(): void
    {
        $argv = ['script.php', 'command', '--option=value', 'argument'];
        $input = $this->parser->parse($argv);
        
        $this->assertEquals(['command', '--option=value', 'argument'], $input->getRawArguments());
    }

    public function testInputInterfaceImplementation(): void
    {
        $argv = ['script.php', 'command'];
        $input = $this->parser->parse($argv);
        
        $this->assertInstanceOf(InputInterface::class, $input);
    }

    public function testParseSpecialCharactersInValues(): void
    {
        $argv = ['script.php', 'command', '--message=Hello World!', '--path=/var/log/app.log'];
        $input = $this->parser->parse($argv);
        
        $this->assertEquals('Hello World!', $input->getOption('message'));
        $this->assertEquals('/var/log/app.log', $input->getOption('path'));
    }

    public function testParseOptionsWithDashes(): void
    {
        $argv = ['script.php', 'command', '--dry-run', '--cache-store=redis'];
        $input = $this->parser->parse($argv);
        
        $this->assertTrue($input->getOption('dry-run'));
        $this->assertEquals('redis', $input->getOption('cache-store'));
    }

    public function testParseNumericValues(): void
    {
        $argv = ['script.php', 'command', '--timeout=300', '--port=8080', '42'];
        $input = $this->parser->parse($argv);
        
        $this->assertEquals('300', $input->getOption('timeout'));
        $this->assertEquals('8080', $input->getOption('port'));
        $this->assertEquals('42', $input->getArgument('arg1'));
    }
}