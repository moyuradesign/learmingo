<?php
declare(strict_types=1);

namespace Learmingo\Services;

final class ReadinessRegisterExport
{
    public static function csv(array $items): string
    {
        $stream = fopen('php://temp', 'w+');
        if (!$stream) { throw new \RuntimeException('Cannot prepare the task register.'); }
        try {
            fwrite($stream, "\xEF\xBB\xBF");
            self::row($stream, ['Record ID','Workflow area','Title','Course','Assessment / reference','Version','Status','Environment','Work owner','Reviewer','Priority','Due date','Score (%)','Completed tasks','Total tasks','Open defects','Open defect titles','Publication decision','Updated at (UTC)']);
            foreach ($items as $item) {
                $tasks = $item['checklist'] ?? [];
                $open = array_filter($item['issues'] ?? [], static fn(array $issue): bool => $issue['status'] !== 'resolved');
                self::row($stream, [
                    $item['id'], $item['view'], $item['title'], $item['course'], $item['assessment'],
                    $item['version'], $item['status'], $item['environment'], $item['work_owner'] ?? '',
                    $item['reviewer'], $item['priority'] ?? 'normal', $item['due_date'] ?? '', $item['score'] ?? '',
                    count(array_filter($tasks, static fn(array $task): bool => !empty($task['done']))), count($tasks), count($open),
                    implode('; ', array_column($open, 'title')), $item['decision'], $item['updated_at'] ?? '',
                ]);
            }
            rewind($stream);
            $csv = stream_get_contents($stream);
            if ($csv === false) { throw new \RuntimeException('Cannot read the task register.'); }
            return $csv;
        } finally {
            fclose($stream);
        }
    }

    private static function row(mixed $stream, array $values): void
    {
        // Treat spreadsheet formula prefixes as text, including prefixes hidden by whitespace.
        $values = array_map(static function (mixed $value): string {
            $value = (string)$value;
            return preg_match('/^[\s\x00-\x1f]*[=+\-@]|^[\t\r\n]/u', $value) ? "'".$value : $value;
        }, $values);
        if (fputcsv($stream, $values, ',', '"', '') === false) {
            throw new \RuntimeException('Cannot write the task register.');
        }
    }
}
