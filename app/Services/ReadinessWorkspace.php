<?php
declare(strict_types=1);
namespace Learmingo\Services;

/** Review metadata only. Deliberately has no course, certificate or deployment adapter. */
final class ReadinessWorkspace
{
    public const VIEWS = ['Assessment design', 'Question banks', 'Instructor review', 'Grading and moderation', 'Certificate templates and issuance', 'Course revision history', 'Release readiness'];
    public const STATES = ['planned', 'in progress', 'in review', 'verified', 'approved'];
    public function __construct(private string $path) {}
    public function run(?array $input = null, int $actor = 0): array
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) { throw new \RuntimeException('Cannot create workspace storage.'); }
        $lock = fopen($this->path . '.lock', 'c');
        if (!$lock || !flock($lock, $input === null ? LOCK_SH : LOCK_EX)) { throw new \RuntimeException('Workspace storage is unavailable.'); }
        try {
            $items = is_file($this->path) ? json_decode((string)file_get_contents($this->path), true, 512, JSON_THROW_ON_ERROR) : [];
            if ($input === null) { return $items; }
            $id = (string)($input['id'] ?? '');
            $old = $id !== '' ? ($items[$id] ?? null) : null;
            if ($id !== '' && !$old) { throw new \InvalidArgumentException('Item not found.'); }
            if ($old && (int)($input['revision'] ?? 0) !== $old['revision']) { throw new \InvalidArgumentException('This item changed. Reload before saving.'); }
            $item = [];
            foreach (['title','course','assessment','version','environment','rule','reviewer','evidence','defects','rollback','decision','details','view'] as $key) {
                if (!is_string($input[$key] ?? '')) { throw new \InvalidArgumentException('Invalid field.'); }
                $item[$key] = trim((string)($input[$key] ?? ''));
                if (strlen($item[$key]) > 12000) { throw new \InvalidArgumentException('Field exceeds 12,000 bytes.'); }
            }
            if ($item['title'] === '' || $item['course'] === '' || $item['version'] === '' || !in_array($item['view'], self::VIEWS, true) || !in_array($item['environment'], ['local','staging','production'], true) || !in_array($item['decision'], ['hold','changes required','acceptance complete'], true)) { throw new \InvalidArgumentException('Complete required fields with valid values.'); }
            foreach (preg_split('/\R/', $item['evidence']) as $url) {
                if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http','https'], true))) { throw new \InvalidArgumentException('Evidence must contain one HTTP or HTTPS URL per line.'); }
            }
            $status = (string)($input['status'] ?? 'planned');
            $previous = $old['status'] ?? 'planned';
            $from = array_search($previous, self::STATES, true); $to = array_search($status, self::STATES, true);
            if ($to === false || (!$old && $status !== 'planned') || ($status !== $previous && $to !== $from + 1 && $status !== 'planned')) { throw new \InvalidArgumentException('Follow the workflow in order, or return to planned.'); }
            // Editing reviewed content invalidates its verification and approvals.
            if ($old && array_diff_assoc(array_diff_key($item, ['decision'=>true]), array_intersect_key($old, array_diff_key($item, ['decision'=>true]))) && $from >= 2 && $status !== 'planned') { throw new \InvalidArgumentException('Return reviewed content to planned before editing it.'); }
            if ($to >= 2 && ($item['reviewer'] === '' || $item['rule'] === '')) { throw new \InvalidArgumentException('A reviewer and grading or acceptance rule are required for review.'); }
            if ($to >= 3 && ($item['evidence'] === '' || $item['rollback'] === '' || $item['defects'] !== 'none' || $item['environment'] === 'production')) { throw new \InvalidArgumentException('Verification requires non-production evidence, rollback notes and defect notes set to “none”.'); }
            if ($status === 'approved' && $item['decision'] !== 'acceptance complete') { throw new \InvalidArgumentException('Acceptance must be complete before approval.'); }
            $id = $old['id'] ?? bin2hex(random_bytes(12));
            $item += ['id'=>$id, 'status'=>$status, 'revision'=>($old['revision'] ?? 0)+1, 'history'=>$old['history'] ?? []];
            $item['history'][] = ['actor'=>$actor, 'at'=>gmdate('c'), 'from'=>$old['status'] ?? null, 'to'=>$status, 'revision'=>$item['revision'], 'decision'=>$item['decision']];
            $items[$id] = $item;
            $temp = tempnam($dir, 'readiness-');
            try {
                if ($temp === false || file_put_contents($temp, json_encode($items, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)) === false || !chmod($temp, 0600) || !rename($temp, $this->path)) { throw new \RuntimeException('Cannot save workspace.'); }
            } finally { if ($temp && is_file($temp)) { unlink($temp); } }
            return $items;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}
