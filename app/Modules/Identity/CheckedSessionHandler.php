<?php

namespace App\Modules\Identity;

use RuntimeException;
use SessionHandlerInterface;

/** Convert silently rejected storage operations into the generic HTTP failure contract. */
class CheckedSessionHandler implements SessionHandlerInterface
{
    public function __construct(private SessionHandlerInterface $inner) {}

    public function open(string $path, string $name): bool
    {
        return $this->inner->open($path, $name);
    }

    public function close(): bool
    {
        return $this->inner->close();
    }

    public function read(string $id): string|false
    {
        $data = $this->inner->read($id);
        if ($data === false) {
            throw new RuntimeException('Session read failed.');
        }

        return $data;
    }

    public function write(string $id, string $data): bool
    {
        $written = $this->inner->write($id, $data);
        // Refresh cached expiry metadata before checking the just-written session.
        clearstatcache();
        if (! $written || $this->inner->read($id) !== $data) {
            throw new RuntimeException('Session write failed.');
        }

        return true;
    }

    public function destroy(string $id): bool
    {
        if (! $this->inner->destroy($id) || $this->inner->read($id) !== '') {
            throw new RuntimeException('Session invalidation failed.');
        }

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->inner->gc($max_lifetime);
    }
}
