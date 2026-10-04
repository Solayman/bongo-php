<?php

namespace System\Console;

/**
 * Base class for all custom CLI commands in app/Commands/.
 *
 *   class SendDigest extends Command
 *   {
 *       protected string $name = 'digest:send';
 *       protected string $description = 'Send daily email digests to subscribed users';
 *
 *       public function handle(array $args, array $options): int
 *       {
 *           $this->info('Sending digests...');
 *           return 0;
 *       }
 *   }
 */
abstract class Command
{
    /** The command name/signature, e.g. "reports:generate" */
    protected string $name = '';

    /** Optional alias for name */
    protected string $signature = '';

    /** The command description displayed in php bongo help */
    protected string $description = '';

    public function getName(): string
    {
        return $this->name !== '' ? $this->name : $this->signature;
    }

    public function getSignature(): string
    {
        return $this->getName();
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Execute the console command.
     * @param array $args Positional arguments passed to the command
     * @param array $options Options passed (e.g. ['force' => true, 'limit' => '10'])
     * @return int 0 for success, non-zero for error/exit code
     */
    abstract public function handle(array $args, array $options): int;

    public function line(string $message): void
    {
        echo $message . PHP_EOL;
    }

    public function info(string $message): void
    {
        echo "\033[32m" . $message . "\033[0m" . PHP_EOL;
    }

    public function warn(string $message): void
    {
        echo "\033[33m" . $message . "\033[0m" . PHP_EOL;
    }

    public function error(string $message): void
    {
        fwrite(STDERR, "\033[31m" . $message . "\033[0m" . PHP_EOL);
    }
}
