<?php
declare(strict_types=1);

namespace Learmingo\Services;

/** Planning metadata and completion rules shared by all seven readiness views. */
final class ReadinessPlanning
{
    public static function normalize(array $input): array
    {
        $owner = ReadinessSchema::text($input, 'work_owner');
        $priority = ReadinessSchema::text($input, 'priority') ?: 'normal';
        $due = ReadinessSchema::text($input, 'due_date', 10);
        if (!in_array($priority, ['low','normal','high','urgent'], true)) {
            throw new \InvalidArgumentException('Choose a valid work priority.');
        }
        if ($due !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $due) || substr($due, 0, 4) === '0000') {
                throw new \InvalidArgumentException('Due date must be a valid YYYY-MM-DD date.');
            }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $due);
            if (!$date || $date->format('Y-m-d') !== $due) {
                throw new \InvalidArgumentException('Due date must be a valid YYYY-MM-DD date.');
            }
        }
        $checklist = self::checklist($input['checklist'] ?? []);
        $issues = [];
        foreach (self::rows($input['issues'] ?? [], 30) as $row) {
            $id = ReadinessSchema::text($row, 'id', 16);
            if ($id !== '' && !preg_match('/^[a-f0-9]{16}$/D', $id)) {
                throw new \InvalidArgumentException('Invalid defect reference.');
            }
            $id = $id ?: bin2hex(random_bytes(8));
            $issue = [
                'id'=>$id,
                'title'=>ReadinessSchema::text($row, 'title'),
                'severity'=>ReadinessSchema::text($row, 'severity'),
                'status'=>ReadinessSchema::text($row, 'status'),
                'assignee'=>ReadinessSchema::text($row, 'assignee'),
                'resolution'=>ReadinessSchema::text($row, 'resolution', 4000),
                'evidence'=>ReadinessSchema::text($row, 'evidence', 2000),
            ];
            if ($issue['title'] === '' || !in_array($issue['severity'], ['low','medium','high','critical'], true)
                || !in_array($issue['status'], ['open','in progress','resolved'], true)) {
                throw new \InvalidArgumentException('Each defect requires a title, severity and status.');
            }
            if ($issue['evidence'] !== '' && !self::safeUrl($issue['evidence'])) {
                throw new \InvalidArgumentException('Defect evidence must be an HTTP or HTTPS URL without credentials.');
            }
            if ($issue['status'] === 'resolved' && ($issue['resolution'] === '' || $issue['evidence'] === '')) {
                throw new \InvalidArgumentException('Resolved defects require resolution notes and an evidence URL.');
            }
            if (isset($issues[$id])) {
                throw new \InvalidArgumentException('Duplicate defect reference.');
            }
            $issues[$id] = $issue;
        }
        return ['work_owner'=>$owner, 'priority'=>$priority, 'due_date'=>$due, 'checklist'=>$checklist, 'issues'=>array_values($issues)];
    }

    public static function checklist(mixed $input): array
    {
        $result = [];
        foreach (self::rows($input, 50) as $row) {
            $title = ReadinessSchema::text($row, 'title');
            $done = $row['done'] ?? '0';
            if ($title === '' || !in_array($done, ['0','1',false,true,0,1], true)) {
                throw new \InvalidArgumentException('Every checklist task needs a title and valid completion state.');
            }
            $result[] = ['title'=>$title, 'done'=>in_array($done, ['1',true,1], true)];
        }
        return $result;
    }

    private static function rows(mixed $input, int $limit): array
    {
        if (!is_array($input) || count($input) > $limit) {
            throw new \InvalidArgumentException('Too many or invalid planning rows.');
        }
        foreach ($input as $row) {
            if (!is_array($row)) { throw new \InvalidArgumentException('Invalid planning row.'); }
        }
        return array_values($input);
    }

    private static function safeUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http','https'], true)
            && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null;
    }

    public static function guardReviewedChanges(array $item, array $old, int $from): void
    {
        if ($from < 2 || $item['status'] === 'planned') { return; }
        foreach (['work_owner'=>'', 'priority'=>'normal', 'due_date'=>''] as $key => $default) {
            if ($item[$key] !== ($old[$key] ?? $default)) {
                throw new \InvalidArgumentException('Return to planned before changing reviewed task ownership, priority or due date.');
            }
        }
        if (array_column($item['checklist'], 'title') !== array_column($old['checklist'] ?? [], 'title')) {
            throw new \InvalidArgumentException('Return to planned before changing the reviewed checklist tasks.');
        }
        if ($from >= 3 && ($item['checklist'] !== ($old['checklist'] ?? []) || $item['issues'] !== ($old['issues'] ?? []))) {
            throw new \InvalidArgumentException('Return to planned before changing verified checklist results or defects.');
        }
        $current = array_column($item['issues'], null, 'id');
        foreach ($old['issues'] ?? [] as $issue) {
            if (!isset($current[$issue['id']]) || $current[$issue['id']]['title'] !== $issue['title']
                || $current[$issue['id']]['severity'] !== $issue['severity']) {
                throw new \InvalidArgumentException('Reviewed defects must be retained. Return to planned before removing or redefining one.');
            }
        }
    }

    public static function complete(array $item): bool
    {
        foreach ($item['checklist'] ?? [] as $task) {
            if (empty($task['done'])) { return false; }
        }
        foreach ($item['issues'] ?? [] as $issue) {
            if (($issue['status'] ?? '') !== 'resolved' || trim($issue['resolution'] ?? '') === ''
                || !self::safeUrl($issue['evidence'] ?? '')) { return false; }
        }
        return true;
    }

    public static function resetResults(array $input): array
    {
        foreach ($input['checklist'] ?? [] as $key => $task) { $input['checklist'][$key]['done'] = false; }
        foreach ($input['issues'] ?? [] as $key => $issue) {
            $input['issues'][$key]['status'] = 'open';
            $input['issues'][$key]['resolution'] = '';
            $input['issues'][$key]['evidence'] = '';
        }
        return $input;
    }
}
