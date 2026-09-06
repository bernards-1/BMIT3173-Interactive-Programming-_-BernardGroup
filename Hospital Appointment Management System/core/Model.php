<?php
/**
 * Base Model implementing ActiveRecord ORM pattern
 */

if (!class_exists('Database')) {
    require_once __DIR__ . '/../db.php';
}

abstract class Model {
    /**
     * @var string Database table associated with the model.
     */
    protected static $table;

    /**
     * @var string Primary key column name.
     */
    protected static $primaryKey = 'id';

    /**
     * @var array Model attributes.
     */
    protected $attributes = [];

    /**
     * @var array Original attributes snapshot for dirty checking.
     */
    protected $original = [];

    /**
     * @var bool Whether the model instance exists in the database.
     */
    protected $exists = false;

    /**
     * @var PDO Database connection.
     */
    protected static $db = null;

    public function __construct(array $attributes = [], bool $exists = false) {
        $this->attributes = $attributes;
        $this->original = $attributes;
        $this->exists = $exists;
    }

    /**
     * Get the PDO database connection.
     */
    protected static function getDb() {
        if (self::$db === null) {
            global $pdo;
            if ($pdo !== null) {
                self::$db = $pdo;
            } elseif (class_exists('Database')) {
                self::$db = Database::getInstance()->getConnection();
            }
        }
        return self::$db;
    }

    /**
     * Explicitly set the database connection (useful for unit tests or switching connections).
     */
    public static function setDb($db): void {
        self::$db = $db;
    }

    /**
     * Get table name.
     */
    public static function getTable(): string {
        if (empty(static::$table)) {
            // Default table name to lowercased pluralized class name
            $shortName = (new ReflectionClass(static::class))->getShortName();
            return strtolower($shortName) . 's';
        }
        return static::$table;
    }

    /**
     * Get primary key column name.
     */
    public static function getPrimaryKey(): string {
        return static::$primaryKey ?? 'id';
    }

    /**
     * Magic getter for attributes.
     */
    public function __get(string $key) {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Magic setter for attributes.
     */
    public function __set(string $key, $value): void {
        $this->attributes[$key] = $value;
    }

    /**
     * Magic isset for attributes.
     */
    public function __isset(string $key): bool {
        return isset($this->attributes[$key]);
    }

    /**
     * Magic unset for attributes.
     */
    public function __unset(string $key): void {
        unset($this->attributes[$key]);
    }

    /**
     * Return all attributes as an array.
     */
    public function toArray(): array {
        return $this->attributes;
    }

    /**
     * Find a record by primary key. Returns Model instance or null.
     */
    public static function find($id): ?static {
        $db = static::getDb();
        $table = static::getTable();
        $pk = static::getPrimaryKey();

        $stmt = $db->prepare("SELECT * FROM `{$table}` WHERE `{$pk}` = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $instance = new static($row, true);
        return $instance;
    }

    /**
     * Find a record by primary key or throw an exception if not found.
     *
     * @throws Exception
     */
    public static function findOrFail($id): static {
        $instance = static::find($id);
        if (!$instance) {
            $table = static::getTable();
            $pk = static::getPrimaryKey();
            throw new Exception("Record with {$pk} '{$id}' not found in table '{$table}'.");
        }
        return $instance;
    }

    /**
     * Retrieve all records as Model instances.
     */
    public static function all(): array {
        $db = static::getDb();
        $table = static::getTable();

        $stmt = $db->query("SELECT * FROM `{$table}`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $results[] = new static($row, true);
        }
        return $results;
    }

    /**
     * Find records matching a specific column and value.
     */
    public static function where(string $column, $value): array {
        $db = static::getDb();
        $table = static::getTable();

        $stmt = $db->prepare("SELECT * FROM `{$table}` WHERE `{$column}` = ?");
        $stmt->execute([$value]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $results[] = new static($row, true);
        }
        return $results;
    }

    /**
     * Create and persist a new model record.
     */
    public static function create(array $attributes): static {
        $instance = new static($attributes, false);
        $instance->save();
        return $instance;
    }

    /**
     * Automated persistence: saves changes to database without manual SQL.
     * Detects whether to perform an UPDATE or an INSERT.
     */
    public function save(): bool {
        $db = static::getDb();
        $table = static::getTable();
        $pk = static::getPrimaryKey();

        if ($this->exists) {
            // Perform UPDATE
            $fields = [];
            $params = [];

            foreach ($this->attributes as $key => $value) {
                if ($key === $pk) continue; // Do not update primary key column
                $fields[] = "`{$key}` = ?";
                $params[] = $value;
            }

            if (empty($fields)) {
                return true; // Nothing to update
            }

            $params[] = $this->attributes[$pk] ?? $this->original[$pk];
            $sql = "UPDATE `{$table}` SET " . implode(', ', $fields) . " WHERE `{$pk}` = ?";
            $stmt = $db->prepare($sql);
            $success = $stmt->execute($params);

            if ($success) {
                $this->original = $this->attributes;
            }
            return $success;
        } else {
            // Perform INSERT
            $columns = [];
            $placeholders = [];
            $params = [];

            foreach ($this->attributes as $key => $value) {
                $columns[] = "`{$key}`";
                $placeholders[] = "?";
                $params[] = $value;
            }

            $colStr = implode(', ', $columns);
            $phStr = implode(', ', $placeholders);
            $sql = "INSERT INTO `{$table}` ({$colStr}) VALUES ({$phStr})";
            $stmt = $db->prepare($sql);
            $success = $stmt->execute($params);

            if ($success) {
                // If auto-increment ID generated, capture it
                $lastId = $db->lastInsertId();
                if ($lastId && empty($this->attributes[$pk])) {
                    $this->attributes[$pk] = $lastId;
                }
                $this->exists = true;
                $this->original = $this->attributes;
            }
            return $success;
        }
    }

    /**
     * Delete a record by primary key (or self via destroy()).
     */
    public static function delete($id = null): bool {
        if (!$id) {
            return false;
        }

        $db = static::getDb();
        $table = static::getTable();
        $pk = static::getPrimaryKey();

        $stmt = $db->prepare("DELETE FROM `{$table}` WHERE `{$pk}` = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Instance method to delete current model instance.
     */
    public function destroy(): bool {
        $pk = static::getPrimaryKey();
        $pkVal = $this->attributes[$pk] ?? null;
        $success = static::delete($pkVal);
        if ($success) {
            $this->exists = false;
        }
        return $success;
    }

    /**
     * Object Reference Helper: belongsTo
     * Satisfies: "use object references instead of foreign keys to represent relationships"
     */
    public function belongsTo(string $relatedClass, ?string $foreignKey = null, ?string $ownerKey = null): ?object {
        if (!class_exists($relatedClass)) {
            return null;
        }

        if ($foreignKey === null) {
            $shortName = (new ReflectionClass($relatedClass))->getShortName();
            $foreignKey = strtolower($shortName) . '_id';
        }

        $fkValue = $this->attributes[$foreignKey] ?? null;
        if ($fkValue === null) {
            return null;
        }

        if (is_subclass_of($relatedClass, Model::class)) {
            return $relatedClass::find($fkValue);
        }

        // Fallback for classes with static find()
        if (method_exists($relatedClass, 'find')) {
            return $relatedClass::find($fkValue);
        }

        return null;
    }

    /**
     * Object Reference Helper: hasMany
     */
    public function hasMany(string $relatedClass, ?string $foreignKey = null): array {
        if (!class_exists($relatedClass) || !is_subclass_of($relatedClass, Model::class)) {
            return [];
        }

        if ($foreignKey === null) {
            $shortName = (new ReflectionClass(static::class))->getShortName();
            $foreignKey = strtolower($shortName) . '_id';
        }

        $pkVal = $this->attributes[static::getPrimaryKey()] ?? null;
        if ($pkVal === null) {
            return [];
        }

        return $relatedClass::where($foreignKey, $pkVal);
    }
}
