<?php

namespace Project\tests;

use PHPUnit\Framework\TestCase;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use mikehaertl\shellcommand\Command;

use function Project\tests\Utils\joinPath;

class CliTest extends TestCase
{
    private TemporaryDirectory $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = (new TemporaryDirectory())->create();
    }

    protected function tearDown(): void
    {
        $this->outputDir->delete();
    }

    private function runCli(string $arguments): Command
    {
        $command = new Command(joinPath(__DIR__, '..', 'bin', 'page-loader') . " {$arguments}");
        $command->execute();

        return $command;
    }

    public function testHelpIsPrinted(): void
    {
        $command = $this->runCli('--help');

        $this->assertStringContainsString('Usage:', $command->getOutput());
    }

    public function testUnreachableHostEndsWithNonZeroExitCode(): void
    {
        $command = $this->runCli("https://badsite.com -o {$this->outputDir->path()}");

        $this->assertNotSame(0, $command->getExitCode());
        $this->assertNotSame('', $command->getError());
    }
}
