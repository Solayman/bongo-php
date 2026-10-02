<?php
namespace System\View;

/**
 * Holds {: block :} content and {: push :} stacks while a page renders.
 */
class SectionManager
{
    protected array $sections = [];
    protected array $sectionStack = [];
    protected array $pushes = [];
    protected array $pushStack = [];

    /** {: block 'name' :} starts capturing; start('name', 'text') sets it directly */
    public function start(string $name, ?string $content = null): void
    {
        if ($content !== null) {
            $this->sections[$name] = $content;
            return;
        }
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function end(): void
    {
        if (!$this->sectionStack) {
            throw new ViewException('{: end :} without a matching {: block :}');
        }
        $name = array_pop($this->sectionStack);
        $this->sections[$name] = ob_get_clean();
    }

    public function set(string $name, string $value): void
    {
        $this->sections[$name] = $value;
    }

    public function get(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    public function startPush(string $stack): void
    {
        $this->pushStack[] = $stack;
        ob_start();
    }

    public function endPush(): void
    {
        if (!$this->pushStack) {
            throw new ViewException('{: end :} without a matching {: push :}');
        }
        $stack = array_pop($this->pushStack);
        $this->pushes[$stack][] = ob_get_clean();
    }

    public function stack(string $stack): string
    {
        return implode('', $this->pushes[$stack] ?? []);
    }

    /** Fresh state for a new page (called by ViewEngine for each top-level render) */
    public function reset(): void
    {
        $this->sections = $this->sectionStack = $this->pushes = $this->pushStack = [];
    }
}
