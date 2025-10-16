<?php
namespace System\View;

class SectionManager
{
    protected array $sections = [];
    protected array $stack = [];

    public function start(string $name): void
    {
        $this->stack[] = $name;
        ob_start();
    }

    public function end(): void
    {
        $name = array_pop($this->stack);
        $this->sections[$name] = ob_get_clean();
    }

    public function set(string $name, string $value): void
    {
        $this->sections[$name] = $value;
    }

    public function get(string $name): string
    {
        return $this->sections[$name] ?? '';
    }
}
