<?php

namespace App\Core;

use App\Traits\DataBaseTrait;
use SessionHandlerInterface;

class SessionHandler implements SessionHandlerInterface
{
    use DataBaseTrait;

    public function open($path, $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read($id): string
    {
        $row = $this->db()->get('user_sessions', '*', ['id' => $id]);
        return $row['data'] ?? '';
    }

    public function write($id, $data): bool
    {
        try {

            if (empty($data)) {
                return true; // Evita salvar sessão vazia
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $this->destroy($id);

            $this->db()->insert('user_sessions', [
                'id' => $id,
                'data' => $data,
                'ip_address' => $ip,
                'user_agent' => $agent,
                'last_activity' => date('Y-m-d H:i:s')
            ]);

            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    public function destroy($id): bool
    {
        $execute = $this->db()->delete('user_sessions', ['id' => $id]);
        return (bool)$execute?->rowCount();
    }

    public function gc($max_lifetime): int|false
    {

        $threshold = date('Y-m-d H:i:s', time() - $max_lifetime);

        $execute = $this->db()->delete('user_sessions', [
            'last_activity[<]' => $threshold
        ]);

        if ($execute) {
            return $execute->rowCount();
        }

        return false;

    }


}
