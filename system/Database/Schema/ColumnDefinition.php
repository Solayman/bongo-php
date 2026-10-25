<?php

namespace System\Database\Schema;

/**
 * One column in a Blueprint. Modifiers are chainable:
 *   $table->string('email', 150)->unique();
 *   $table->decimal('price', 10, 2)->default(0);
 *   $table->foreignId('category_id')->nullable()->references('categories')->onDelete('set null');
 */
class ColumnDefinition
{
    public bool $nullable = false;
    public bool $hasDefault = false;
    public mixed $default = null;
    public bool $unsigned = false;
    public bool $autoIncrement = false;
    public bool $primary = false;
    public bool $unique = false;
    public bool $index = false;
    public ?string $onUpdate = null;
    public ?string $comment = null;
    public ?string $after = null;
    public bool $change = false;

    /** Foreign key: [table, column, onDelete, onUpdate] */
    public ?array $foreign = null;

    public function __construct(public string $name, public string $type)
    {
    }

    public function nullable(bool $value = true): static
    {
        $this->nullable = $value;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->hasDefault = true;
        $this->default = $value;
        return $this;
    }

    /** Default to the current time, e.g. ->useCurrent() on a timestamp */
    public function useCurrent(): static
    {
        return $this->default(new Expression('CURRENT_TIMESTAMP'));
    }

    public function useCurrentOnUpdate(): static
    {
        $this->onUpdate = 'CURRENT_TIMESTAMP';
        return $this;
    }

    public function unsigned(): static
    {
        $this->unsigned = true;
        return $this;
    }

    public function autoIncrement(): static
    {
        $this->autoIncrement = true;
        return $this;
    }

    public function primary(): static
    {
        $this->primary = true;
        return $this;
    }

    public function unique(): static
    {
        $this->unique = true;
        return $this;
    }

    public function index(): static
    {
        $this->index = true;
        return $this;
    }

    public function comment(string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    /** Only for Schema::table(): place the new column after another one */
    public function after(string $column): static
    {
        $this->after = $column;
        return $this;
    }

    /** Modify an existing column definition in an ALTER TABLE */
    public function change(): static
    {
        $this->change = true;
        return $this;
    }

    /** Make this column a foreign key to $table.$column */
    public function references(string $table, string $column = 'id'): static
    {
        $this->foreign = [$table, $column, 'RESTRICT', 'RESTRICT'];
        return $this;
    }

    /** cascade | set null | restrict | no action */
    public function onDelete(string $action): static
    {
        $this->assertForeign();
        $this->foreign[2] = self::action($action);
        return $this;
    }

    public function onUpdate(string $action): static
    {
        $this->assertForeign();
        $this->foreign[3] = self::action($action);
        return $this;
    }

    protected function assertForeign(): void
    {
        if ($this->foreign === null) {
            throw new \LogicException("Call references() before onDelete()/onUpdate() on column [{$this->name}]");
        }
    }

    protected static function action(string $action): string
    {
        $action = strtoupper(trim($action));
        if (!in_array($action, ['CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION'], true)) {
            throw new \InvalidArgumentException("Invalid foreign key action [$action]");
        }
        return $action;
    }
}
