<?php
namespace Tests\Unit;

use System\Console\Command;
use System\Console\Generator;
use System\Testing\TestCase;

class SampleCustomCommand extends Command
{
    protected string $signature = 'test:sample';
    protected string $description = 'A test sample command';

    public function handle(array $args, array $options): int
    {
        $this->line("Running sample with {$args[0]}");
        $this->info("Flag is " . ($options['dry-run'] ?? 'false'));
        return 0;
    }
}

class CommandTest extends TestCase
{
    public function testBaseCommandExecutionAndOutputCapturing(): void
    {
        $cmd = new SampleCustomCommand();
        $this->assertSame('test:sample', $cmd->getSignature());
        $this->assertSame('A test sample command', $cmd->getDescription());

        ob_start();
        $exitCode = $cmd->handle(['item123'], ['dry-run' => 'true']);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertContains('Running sample with item123', $output);
        $this->assertContains('Flag is true', $output);
    }

    public function testGeneratorCreatesValidCommandFile(): void
    {
        $dir = sys_get_temp_dir() . '/bongo_cmd_gen_' . bin2hex(random_bytes(4));
        mkdir($dir);

        try {
            $g = new Generator($dir);
            $relPath = $g->command('ProcessReports');
            $fullPath = "$dir/$relPath";

            $this->assertSame('app/Commands/ProcessReports.php', $relPath);
            $this->assertTrue(file_exists($fullPath));

            $content = (string) file_get_contents($fullPath);
            $this->assertContains('namespace App\Commands;', $content);
            $this->assertContains('class ProcessReports extends Command', $content);
            $this->assertContains("protected string \$name = 'process:reports';", $content);

            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($fullPath), $out, $code);
            $this->assertSame(0, $code, 'Generated command is valid PHP syntax');
        } finally {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                $f->isDir() ? @rmdir($f) : @unlink($f);
            }
            @rmdir($dir);
        }
    }
}
