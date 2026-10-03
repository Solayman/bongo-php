<?php
namespace System\View;

/**
 * HTML that is already safe (a component's $slot): {{ $slot }} prints it without escaping.
 */
class HtmlString implements \Stringable
{
    public function __construct(protected string $html) {}

    public function __toString(): string
    {
        return $this->html;
    }

    /** True when the slot is empty or only whitespace: @if($slot->isEmpty()) */
    public function isEmpty(): bool
    {
        return trim($this->html) === '';
    }
}
