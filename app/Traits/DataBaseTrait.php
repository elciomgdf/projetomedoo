<?php

namespace App\Traits;

use Medoo\Medoo;

/**
 * Trait para isolar o tratamento de banco de dados
 */
trait DataBaseTrait
{
    protected ?Medoo $query = null;

    protected bool $fillModel = false;

    /**
     * @return Medoo
     */
    protected function db(): Medoo
    {
        if ($this->query instanceof Medoo) {
            return $this->query;
        }

        $this->query = new Medoo([
            'type' => 'mysql',
            'database' => $_ENV['DB_NAME'],
            'host' => $_ENV['DB_HOST'],
            'username' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
            'charset' => 'utf8mb4',
            'error' => \PDO::ERRMODE_EXCEPTION
        ]);

        return $this->query;
    }

    /**
     * Verifica se a Tabela existe
     * @param string|null $table
     * @return string|null
     */
    private function getTable(string $table = null): ?string
    {
        if (!$table && property_exists($this, 'table')) {
            $table = $this->table;
            $this->fillModel = true;
        }

        if (!$table) {
            throw new \RuntimeException('Nome da tabela não definido');
        }

        $stmt = $this->db()->pdo->prepare("SHOW TABLES LIKE '$table'");
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException("A tabela $table não existe");
        }

        return $table;
    }

    protected function findById(int $id, string $table = null): array|bool|null
    {
        $table = $this->getTable($table);
        $result = $this->db()->get($table, '*', ['id' => $id]);

        return $result ?: null;
    }

    public function findOneBy(array $where, string $table = null, array $ignore = []): array|bool|null
    {
        $table = $this->getTable($table);

        if (empty($where)) return null;

        $conditions = ['AND' => $where];

        if ($ignore) {
            foreach ($ignore as $field => $value) {
                $conditions['AND'][$field . '[!]'] = $value;
            }
        }

        $data = $this->db()->get($table, '*', $conditions);

        if ($this->fillModel && $data) {
            $this->initData = $data;
            $this->fill($data);
        }

        if ($data && property_exists($this, 'hidden')) {
            foreach ($this->hidden as $field) {
                unset($data[$field]);
            }
        }

        return $data ?: null;
    }

    protected function update(int $id, array $data, string $table = null): bool
    {
        $table = $this->getTable($table);
        $this->db()->update($table, $data, ['id' => $id]);
        return $this->db()->error === [null, null, null];
    }

    protected function insert(array $data, string $table = null): int
    {
        $table = $this->getTable($table);
        $this->db()->insert($table, $data);
        return (int)$this->db()->id();
    }

    public function delete(int $id, string $table = null): bool
    {
        $table = $this->getTable($table);
        $execute = $this->db()->delete($table, ['id' => $id]);
        return (bool)$execute?->rowCount();
    }

    public function all(int $limit = 1000, string $orderBy = 'id', string $table = null): array
    {
        $table = $this->getTable($table);
        $items = $this->db()->select($table, '*', [
            "ORDER" => [$orderBy => 'ASC'],
            "LIMIT" => $limit
        ]);

        foreach ($items as &$row) {
            if (property_exists($this, 'hidden')) {
                foreach ($this->hidden as $field) {
                    unset($row[$field]);
                }
            }
        }

        return $items;
    }

    public function paginate(int $page = 1, int $perPage = 10, array $where = [], string $orderBy = 'id', string $direction = 'ASC', string $table = null): array
    {

        $table = $this->getTable($table);
        $direction = sanitizeDirection($direction);
        $offset = ($page - 1) * $perPage;

        $conditions = [
            'ORDER' => [$orderBy => $direction],
            'LIMIT' => [$offset, $perPage]
        ];

        if ($where) {
            $conditions['AND'] = $where;
        }

        $items = $this->db()->select($table, '*', $conditions);

        foreach ($items as &$row) {
            if (property_exists($this, 'hidden')) {
                foreach ($this->hidden as $field) {
                    unset($row[$field]);
                }
            }
        }

        $total = $this->db()->count($table, '*', $where);
        $total_pages = ceil($total / $perPage);

        return [
            'items' => $items,
            'current_page' => $page,
            'next_page' => $page < $total_pages ? $page + 1 : null,
            'previous_page' => ($page - 1) ?: null,
            'per_page' => $perPage,
            'total_pages' => $total_pages,
            'total' => $total
        ];
    }

    public function beginTransaction(): void
    {
        $this->db()->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->db()->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->db()->pdo->rollBack();
    }
}
