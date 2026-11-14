<?php
declare(strict_types=1);

namespace Learmingo\Services;

/** Acceptance records only; deliberately has no LMS publication or issuance adapter. */
final class ReadinessWorkspace
{
    public const VIEWS = ['Assessment design', 'Question banks', 'Instructor review', 'Grading and moderation', 'Certificate templates and issuance', 'Course revision history', 'Release readiness'];
    public const STATES = ['planned', 'in progress', 'in review', 'verified', 'approved'];
    private const DESIGN = ['title','course','assessment','version','environment','rule','reviewer','details','view','fields'];
    private const CONTENT = ['title','course','assessment','version','environment','rule','reviewer','evidence','defects','rollback','decision','details','view','score','fields','status','dependencies'];

    public function __construct(private string $path) {}

    public function run(?array $input = null, int $actor = 0): array
    {
        $storage = new ReadinessStorage($this->path);
        if ($input === null) {
            return $storage->read();
        }
        if ($actor < 1) {
            throw new \InvalidArgumentException('An authenticated administrator is required.');
        }
        return $storage->change(function (array $items) use ($input, $actor): array {
            $id = ReadinessSchema::text($input, 'id', 24);
            $old = $id === '' ? null : ($items[$id] ?? null);
            if ($id !== '' && !$old) {
                throw new \InvalidArgumentException('Item not found.');
            }
            if ($old && filter_var($input['revision'] ?? null, FILTER_VALIDATE_INT) !== $old['revision']) {
                throw new \InvalidArgumentException('This item changed. Reload the latest records before saving.');
            }
            $action = ReadinessSchema::text($input, 'action');
            if (!in_array($action, ['', 'save', 'restore'], true)) {
                throw new \InvalidArgumentException('Unknown record action.');
            }
            $restored = null;
            if ($action === 'restore') {
                $restored = filter_var($input['snapshot_revision'] ?? null, FILTER_VALIDATE_INT);
                $snapshot = $old['snapshots'][$restored] ?? null;
                if (!$old || !$snapshot) {
                    throw new \InvalidArgumentException('That content snapshot is unavailable.');
                }
                $input = array_merge($snapshot, [
                    'status'=>'planned', 'decision'=>'hold', 'evidence'=>'', 'score'=>'',
                    'defects'=>'', 'rollback'=>'', 'fields'=>$snapshot['fields'] ?? [],
                ]);
                // Historical release acceptance assertions are not reusable proof.
                if (($input['view'] ?? '') === 'Release readiness') {
                    foreach (ReadinessSchema::views()['Release readiness'] as $key => $definition) {
                        if ($definition['gate'] ?? false) { $input['fields'][$key] = 'pending'; }
                    }
                }
            }
            $item = $this->normalize($input);
            $previous = $old['status'] ?? 'planned';
            $from = array_search($previous, self::STATES, true);
            $to = array_search($item['status'], self::STATES, true);
            if ($to === false || (!$old && $item['status'] !== 'planned')
                || ($item['status'] !== $previous && $to !== $from + 1 && $item['status'] !== 'planned')) {
                throw new \InvalidArgumentException('Follow the workflow in order, or return to planned.');
            }
            if ($old && $from >= 2 && $item['status'] !== 'planned') {
                foreach (self::DESIGN as $key) {
                    $prior = $key === 'fields' ? ReadinessSchema::fields($old['view'], $old['fields'] ?? []) : ($old[$key] ?? '');
                    $current = $item[$key];
                    if ($key === 'fields' && $from === 2) {
                        foreach (ReadinessSchema::views()[$old['view']] as $field => $definition) {
                            if ($definition['review'] ?? false) { unset($prior[$field], $current[$field]); }
                        }
                    }
                    if ($current !== $prior) {
                        throw new \InvalidArgumentException('Return to planned before changing reviewed design or ownership.');
                    }
                }
                if ($from >= 3) {
                    foreach (['evidence','defects','rollback','score'] as $key) {
                        if ($item[$key] !== ($old[$key] ?? '')) {
                            throw new \InvalidArgumentException('Return to planned before changing verified evidence or results.');
                        }
                    }
                }
            }
            if ($item['status'] === 'planned') {
                $item['decision'] = 'hold';
            }
            if ($to >= 2) {
                if ($item['reviewer'] === '' || $item['rule'] === '') {
                    throw new \InvalidArgumentException('A reviewer and grading or acceptance rule are required for review.');
                }
                foreach (ReadinessSchema::views()[$item['view']] as $key => $definition) {
                    if (($definition['required'] ?? false) && $item['fields'][$key] === '') {
                        throw new \InvalidArgumentException('Complete before review: '.$definition['label']);
                    }
                }
            }
            if ($to >= 3) {
                if ($item['evidence'] === '' || $item['rollback'] === '' || $item['defects'] !== 'none' || $item['environment'] === 'production') {
                    throw new \InvalidArgumentException('Verification requires non-production evidence, rollback notes and resolved defects (enter “none”).');
                }
            }
            if ($to >= 3 && $item['decision'] === 'changes required') {
                throw new \InvalidArgumentException('Return to planned when further changes are required.');
            }
            if ($item['status'] === 'approved' && $item['decision'] !== 'acceptance complete') {
                throw new \InvalidArgumentException('Record acceptance complete before approval.');
            }
            if ($item['decision'] === 'acceptance complete' && $item['status'] !== 'approved') {
                throw new \InvalidArgumentException('Acceptance complete is available only at the approved step.');
            }
            $item['dependencies'] = [];
            if ($item['view'] === 'Release readiness' && $to >= 3) {
                foreach (ReadinessSchema::views()['Release readiness'] as $key => $definition) {
                    if (($definition['gate'] ?? false) && $item['fields'][$key] !== 'complete') {
                        throw new \InvalidArgumentException('Release acceptance is pending: '.$definition['label']);
                    }
                }
                $summary = self::releaseSummary($items, $item['course'], $item['version'], $item['environment']);
                if ($summary['blockers']) {
                    throw new \InvalidArgumentException('Release blocked: '.implode('; ', $summary['blockers']));
                }
                $item['dependencies'] = $summary['dependencies'];
            }
            $id = $old['id'] ?? bin2hex(random_bytes(12));
            $item['id'] = $id;
            $item['revision'] = ($old['revision'] ?? 0) + 1;
            $item['created_at'] = $old['created_at'] ?? gmdate('c');
            $item['updated_at'] = gmdate('c');
            $item['history'] = $old['history'] ?? [];
            $item['snapshots'] = $old['snapshots'] ?? [];
            if ($old && !isset($item['snapshots'][$old['revision']])) {
                $item['snapshots'][$old['revision']] = self::snapshot($old);
            }
            $this->record($item, $actor, $old['status'] ?? null, $restored === null ? 'save' : 'restore', $restored);
            $items[$id] = $item;
            // Reopening, modifying or adding a dependency invalidates any release acceptance in that scope.
            if ($item['view'] !== 'Release readiness' || ($old && $old['view'] !== 'Release readiness')) {
                foreach ($items as $releaseId => &$release) {
                    if ($releaseId === $id || $release['view'] !== 'Release readiness'
                        || !in_array($release['status'], ['verified','approved'], true)) { continue; }
                    if (!self::sameScope($release, $item) && (!$old || !self::sameScope($release, $old))) { continue; }
                    $before = $release['status'];
                    $release['snapshots'][$release['revision']] = self::snapshot($release);
                    $release['status'] = 'planned';
                    $release['decision'] = 'hold';
                    $release['dependencies'] = [];
                    $release['revision']++;
                    $release['updated_at'] = gmdate('c');
                    $this->record($release, $actor, $before, 'dependency changed: '.$id);
                }
                unset($release);
            }
            return $items;
        });
    }

    private function normalize(array $input): array
    {
        $item = [];
        foreach (['title','course','assessment','version','environment','rule','reviewer','evidence','defects','rollback','decision','details','view','score'] as $key) {
            $item[$key] = ReadinessSchema::text($input, $key, in_array($key, ['rule','evidence','defects','rollback','details'], true) ? 12000 : 500);
        }
        $item['status'] = ReadinessSchema::text($input, 'status');
        if ($item['status'] === '') { $item['status'] = 'planned'; }
        if ($item['title'] === '' || $item['course'] === '' || $item['version'] === ''
            || !in_array($item['view'], self::VIEWS, true)
            || !in_array($item['environment'], ['local','staging','production'], true)
            || !in_array($item['decision'], ['hold','changes required','acceptance complete'], true)) {
            throw new \InvalidArgumentException('Complete the title, course, version and valid workspace selections.');
        }
        $links = [];
        foreach (preg_split('/\R/u', $item['evidence']) as $url) {
            $url = trim($url);
            if ($url === '') { continue; }
            if (!filter_var($url, FILTER_VALIDATE_URL)
                || !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http','https'], true)
                || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
                throw new \InvalidArgumentException('Use one HTTP or HTTPS evidence URL per line, without embedded credentials.');
            }
            $links[] = $url;
        }
        $item['evidence'] = implode("\n", array_unique($links));
        if ($item['score'] !== '' && (!is_numeric($item['score']) || (float)$item['score'] < 0 || (float)$item['score'] > 100)) {
            throw new \InvalidArgumentException('Score must be a percentage between 0 and 100.');
        }
        $item['fields'] = ReadinessSchema::fields($item['view'], $input['fields'] ?? []);
        return $item;
    }

    private static function snapshot(array $item): array
    {
        return array_intersect_key($item, array_flip(self::CONTENT));
    }

    private function record(array &$item, int $actor, ?string $from, string $event, ?int $restored = null): void
    {
        $item['snapshots'][$item['revision']] = self::snapshot($item);
        $item['history'][] = [
            'actor'=>$actor, 'at'=>gmdate('c'), 'from'=>$from, 'to'=>$item['status'],
            'revision'=>$item['revision'], 'decision'=>$item['decision'], 'event'=>$event,
            'restored_revision'=>$restored,
        ];
    }

    private static function sameScope(array $a, array $b): bool
    {
        return $a['course'] === $b['course'] && $a['version'] === $b['version'] && $a['environment'] === $b['environment'];
    }

    private static function acceptedDependency(array $item): bool
    {
        if ($item['status'] !== 'approved' || $item['decision'] !== 'acceptance complete'
            || !in_array($item['environment'], ['local','staging'], true)
            || ($item['defects'] ?? '') !== 'none') { return false; }
        foreach (['rule','reviewer','evidence','rollback'] as $key) {
            if (trim((string)($item[$key] ?? '')) === '') { return false; }
        }
        try {
            $fields = ReadinessSchema::fields($item['view'], $item['fields'] ?? []);
        } catch (\InvalidArgumentException) {
            return false;
        }
        foreach (ReadinessSchema::views()[$item['view']] as $key => $definition) {
            if (($definition['required'] ?? false) && $fields[$key] === '') { return false; }
        }
        return true;
    }

    public static function releaseSummary(array $items, string $course, string $version, string $environment): array
    {
        $blockers = []; $groups = []; $dependencies = [];
        foreach (array_slice(self::VIEWS, 0, -1) as $view) {
            $matching = array_filter($items, static fn(array $item): bool => $item['view'] === $view
                && $item['course'] === $course && $item['version'] === $version && $item['environment'] === $environment);
            $approved = 0;
            foreach ($matching as $item) {
                $dependencies[$item['id']] = $item['revision'];
                if (self::acceptedDependency($item)) { $approved++; }
            }
            if (!$matching) { $blockers[] = $view.' has no records'; }
            elseif ($approved !== count($matching)) { $blockers[] = $view.' has unapproved records'; }
            $groups[] = ['view'=>$view, 'total'=>count($matching), 'approved'=>$approved];
        }
        if ($environment === 'production') { $blockers[] = 'Use local or staging acceptance evidence'; }
        ksort($dependencies);
        return ['groups'=>$groups, 'blockers'=>$blockers, 'dependencies'=>$dependencies, 'production_enabled'=>false];
    }
}
