<?php
declare(strict_types=1);

namespace Learmingo\Services;

final class ReadinessTemplates
{
    public function __construct(private string $path) {}

    public function run(?array $input = null, int $actor = 0): array
    {
        $store = new ReadinessStorage($this->path);
        if ($input === null) { return $store->read(); }
        if ($actor < 1) { throw new \InvalidArgumentException('An administrator is required.'); }
        return $store->change(static function (array $templates) use ($input, $actor): array {
            $id = ReadinessSchema::text($input, 'template_id', 24);
            $old = $id === '' ? null : ($templates[$id] ?? null);
            if ($id !== '' && !$old) { throw new \InvalidArgumentException('Template not found.'); }
            if ($old && filter_var($input['template_revision'] ?? null, FILTER_VALIDATE_INT) !== $old['revision']) {
                throw new \InvalidArgumentException('Template changed. Reload before updating it.');
            }
            $action = ReadinessSchema::text($input, 'action');
            if ($action === 'delete-template') {
                if (!$old) { throw new \InvalidArgumentException('Select a saved template.'); }
                unset($templates[$id]);
                return $templates;
            }
            if ($action !== 'save-template') { throw new \InvalidArgumentException('Unknown template action.'); }
            $name = ReadinessSchema::text($input, 'template_name');
            $view = ReadinessSchema::text($input, 'view');
            if ($name === '' || !in_array($view, ReadinessWorkspace::VIEWS, true)) {
                throw new \InvalidArgumentException('A template name and valid view are required.');
            }
            $fields = ReadinessSchema::fields($view, $input['fields'] ?? []);
            // Reusable configuration never carries acceptance results into a new record.
            foreach (['installation','permissions','backup','devices','issuance','outcome'] as $key) {
                if (array_key_exists($key, $fields)) { $fields[$key] = 'pending'; }
            }
            if (array_key_exists('issued_count', $fields)) { $fields['issued_count'] = ''; }
            $id = $old['id'] ?? bin2hex(random_bytes(12));
            $templates[$id] = [
                'id'=>$id, 'name'=>$name, 'view'=>$view, 'fields'=>$fields,
                'rule'=>ReadinessSchema::text($input, 'rule', 12000),
                'details'=>ReadinessSchema::text($input, 'details', 12000),
                'revision'=>($old['revision'] ?? 0)+1, 'actor'=>$actor, 'updated_at'=>gmdate('c'),
            ];
            return $templates;
        });
    }
}
