<?php
namespace System\View;

/**
 * Holds @section content and @push stacks while a page renders.
 */
class SectionManager
{
    protected array $sections = [];
    protected array $sectionStack = [];
    protected array $pushes = [];
    protected array $pushStack = [];
    protected array $onceSeen = [];

    /** @section('name') starts capturing; @section('name', 'text') sets it directly */
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
            throw new ViewException('@endsection without a matching @section');
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
            throw new ViewException('@endpush without a matching @push');
        }
        $stack = array_pop($this->pushStack);
        $this->pushes[$stack][] = ob_get_clean();
    }

    public function stack(string $stack): string
    {
        return implode('', $this->pushes[$stack] ?? []);
    }

    /** @once: true the first time this id is seen on the current page */
    public function once(string $id): bool
    {
        if (isset($this->onceSeen[$id])) {
            return false;
        }
        return $this->onceSeen[$id] = true;
    }

    /** Fresh state for a new page (called by ViewEngine for each top-level render) */
    public function reset(): void
    {
        $this->sections = $this->sectionStack = $this->pushes = $this->pushStack = $this->onceSeen = [];
    }
}
