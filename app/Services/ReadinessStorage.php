<?php
declare(strict_types=1);

namespace Learmingo\Services;

/** Private, atomic single-host storage. Never silently resets unreadable records. */
final class ReadinessStorage
{
    public function __construct(private string $path) {}

    public function read(): array
    {
        return $this->access(null);
    }

    public function change(callable $change): array
    {
        return $this->access($change);
    }

    private function access(?callable $change): array
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create workspace storage.');
        }
        if (is_link($dir) || is_link($this->path) || is_link($this->path.'.lock')) {
            throw new \RuntimeException('Invalid workspace storage location.');
        }
        $lock = fopen($this->path.'.lock', 'c');
        if (!$lock) {
            throw new \RuntimeException('Workspace storage is unavailable.');
        }
        try {
            if (!flock($lock, $change === null ? LOCK_SH : LOCK_EX)) {
                throw new \RuntimeException('Cannot lock workspace storage.');
            }
            $data = [];
            if (is_file($this->path)) {
                $raw = file_get_contents($this->path);
                if ($raw === false) {
                    throw new \RuntimeException('Cannot read workspace storage.');
                }
                $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($data)) {
                    throw new \RuntimeException('Invalid workspace storage.');
                }
            }
            if ($change === null) {
                return $data;
            }
            $data = $change($data);
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $temp = tempnam($dir, 'readiness-');
            try {
                if ($temp === false || !chmod($temp, 0600)
                    || file_put_contents($temp, $json) !== strlen($json)
                    || !rename($temp, $this->path)) {
                    throw new \RuntimeException('Cannot save workspace.');
                }
            } finally {
                if ($temp !== false && is_file($temp)) {
                    unlink($temp);
                }
            }
            return $data;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
