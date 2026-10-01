<?php
namespace System\Database\Schema;

/**
 * Describes a table's columns and indexes and compiles them to MySQL.
 * Used through Schema::create() and Schema::table().
 */
class Blueprint
{
    /** @var ColumnDefinition[] */
    protected array $columns = [];
    protected array $indexes = [];     // [type, columns[], name]
    protected array $drops = [];       // ['column'|'index'|'foreign', name]
    protected array $renames = [];     // [from, to]

    public string $engine = 'InnoDB';
    public string $charset = 'utf8mb4';
    public string $collation = 'utf8mb4_unicode_ci';

    public function __construct(public string $table) {}

    // ------------------------------------------------------------------
    // Column types
    // ------------------------------------------------------------------

    public function addColumn(string $name, string $type): ColumnDefinition
    {
        return $this->columns[] = new ColumnDefinition($name, $type);
    }

    /** Auto-increment BIGINT UNSIGNED primary key */
    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->addColumn($name, 'BIGINT')->unsigned()->autoIncrement()->primary();
    }

    /** BIGINT UNSIGNED column meant for ->references() */
    public function foreignId(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'BIGINT')->unsigned();
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->addColumn($name, "VARCHAR($length)");
    }

    public function char(string $name, int $length = 255): ColumnDefinition
    {
        return $this->addColumn($name, "CHAR($length)");
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'TEXT');
    }

    public function longText(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'LONGTEXT');
    }

    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'INT');
    }

    public function tinyInteger(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'TINYINT');
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'BIGINT');
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn($name, "DECIMAL($precision, $scale)");
    }

    public function float(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'DOUBLE');
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'TINYINT(1)');
    }

    public function enum(string $name, array $values): ColumnDefinition
    {
        $list = implode(', ', array_map([self::class, 'quote'], $values));
        return $this->addColumn($name, "ENUM($list)");
    }

    public function json(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'JSON');
    }

    public function date(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'DATE');
    }

    public function dateTime(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'DATETIME');
    }

    public function timestamp(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'TIMESTAMP');
    }

    /** created_at + updated_at, filled in by MySQL */
    public function timestamps(): void
    {
        $this->timestamp('created_at')->useCurrent();
        $this->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
    }

    // ------------------------------------------------------------------
    // Indexes and changes
    // ------------------------------------------------------------------

    public function index(string|array $columns, ?string $name = null): void
    {
        $this->indexes[] = ['INDEX', (array) $columns, $name];
    }

    public function unique(string|array $columns, ?string $name = null): void
    {
        $this->indexes[] = ['UNIQUE', (array) $columns, $name];
    }

    public function dropColumn(string ...$columns): void
    {
        foreach ($columns as $column) $this->drops[] = ['column', $column];
    }

    public function dropIndex(string $name): void
    {
        $this->drops[] = ['index', $name];
    }

    public function dropForeign(string $name): void
    {
        $this->drops[] = ['foreign', $name];
    }

    public function renameColumn(string $from, string $to): void
    {
        $this->renames[] = [$from, $to];
    }

    // ------------------------------------------------------------------
    // SQL
    // ------------------------------------------------------------------

    public function toCreateSql(): string
    {
        $lines = [];
        $primary = [];

        foreach ($this->columns as $column) {
            $lines[] = $this->columnSql($column);
            if ($column->primary) $primary[] = self::wrap($column->name);
        }
        if ($primary) {
            $lines[] = 'PRIMARY KEY (' . implode(', ', $primary) . ')';
        }
        foreach ($this->indexDefinitions() as $definition) {
            $lines[] = $definition;
        }
        foreach ($this->foreignDefinitions() as $definition) {
            $lines[] = $definition;
        }

        return 'CREATE TABLE ' . self::wrap($this->table) . " (\n    " . implode(",\n    ", $lines) . "\n)"
            . " ENGINE={$this->engine} DEFAULT CHARSET={$this->charset} COLLATE={$this->collation}";
    }

    public function toAlterSql(): ?string
    {
        $parts = [];

        foreach ($this->drops as [$kind, $name]) {
            $parts[] = match ($kind) {
                'column'  => 'DROP COLUMN ' . self::wrap($name),
                'index'   => 'DROP INDEX ' . self::wrap($name),
                'foreign' => 'DROP FOREIGN KEY ' . self::wrap($name),
            };
        }
        foreach ($this->renames as [$from, $to]) {
            $parts[] = 'RENAME COLUMN ' . self::wrap($from) . ' TO ' . self::wrap($to);
        }
        foreach ($this->columns as $column) {
            $parts[] = 'ADD COLUMN ' . $this->columnSql($column)
                . ($column->after ? ' AFTER ' . self::wrap($column->after) : '');
            if ($column->primary) $parts[] = 'ADD PRIMARY KEY (' . self::wrap($column->name) . ')';
        }
        foreach ($this->indexDefinitions() as $definition) {
            $parts[] = 'ADD ' . $definition;
        }
        foreach ($this->foreignDefinitions() as $definition) {
            $parts[] = 'ADD ' . $definition;
        }

        return $parts ? 'ALTER TABLE ' . self::wrap($this->table) . "\n    " . implode(",\n    ", $parts) : null;
    }

    protected function columnSql(ColumnDefinition $c): string
    {
        $sql = self::wrap($c->name) . ' ' . $c->type;
        if ($c->unsigned) $sql .= ' UNSIGNED';
        $sql .= $c->nullable ? ' NULL' : ' NOT NULL';
        if ($c->autoIncrement) $sql .= ' AUTO_INCREMENT';
        if ($c->hasDefault) $sql .= ' DEFAULT ' . self::value($c->default);
        if ($c->onUpdate) $sql .= ' ON UPDATE ' . $c->onUpdate;
        if ($c->comment !== null) $sql .= ' COMMENT ' . self::quote($c->comment);
        return $sql;
    }

    protected function indexDefinitions(): array
    {
        $definitions = [];

        foreach ($this->columns as $column) {
            if ($column->unique) $definitions[] = $this->indexSql('UNIQUE', [$column->name], null);
            if ($column->index || $column->foreign) $definitions[] = $this->indexSql('INDEX', [$column->name], null);
        }
        foreach ($this->indexes as [$type, $columns, $name]) {
            $definitions[] = $this->indexSql($type, $columns, $name);
        }

        return $definitions;
    }

    protected function indexSql(string $type, array $columns, ?string $name): string
    {
        $name ??= $this->table . '_' . implode('_', $columns) . ($type === 'UNIQUE' ? '_unique' : '_index');
        $keyword = $type === 'UNIQUE' ? 'UNIQUE KEY' : 'INDEX';
        return "$keyword " . self::wrap($name) . ' (' . implode(', ', array_map([self::class, 'wrap'], $columns)) . ')';
    }

    protected function foreignDefinitions(): array
    {
        $definitions = [];
        foreach ($this->columns as $column) {
            if (!$column->foreign) continue;
            [$table, $references, $onDelete, $onUpdate] = $column->foreign;
            $definitions[] = 'CONSTRAINT ' . self::wrap("{$this->table}_{$column->name}_foreign")
                . ' FOREIGN KEY (' . self::wrap($column->name) . ')'
                . ' REFERENCES ' . self::wrap($table) . ' (' . self::wrap($references) . ")"
                . " ON DELETE $onDelete ON UPDATE $onUpdate";
        }
        return $definitions;
    }

    public static function wrap(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public static function quote(string $value): string
    {
        return "'" . str_replace(["\\", "'"], ["\\\\", "''"], $value) . "'";
    }

    protected static function value(mixed $value): string
    {
        return match (true) {
            $value instanceof Expression => $value->sql,
            $value === null              => 'NULL',
            is_bool($value)              => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            default                      => self::quote((string) $value),
        };
    }
}
