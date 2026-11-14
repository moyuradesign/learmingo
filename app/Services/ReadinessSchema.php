<?php
declare(strict_types=1);

namespace Learmingo\Services;

/** Shared form definitions and server validation for acceptance metadata. */
final class ReadinessSchema
{
    public static function views(): array
    {
        return [
            'Assessment design' => [
                'outcomes' => ['label'=>'Learning outcomes', 'type'=>'textarea', 'required'=>true],
                'type' => ['label'=>'Assessment type', 'type'=>'select', 'options'=>['quiz','assignment','exam','practical'], 'required'=>true],
                'pass_score' => ['label'=>'Pass threshold (%)', 'type'=>'number', 'min'=>0, 'max'=>100, 'required'=>true],
                'attempts' => ['label'=>'Allowed attempts', 'type'=>'number', 'min'=>1, 'max'=>100],
                'minutes' => ['label'=>'Time limit (minutes; 0 = untimed)', 'type'=>'number', 'min'=>0, 'max'=>10080],
                'accessibility' => ['label'=>'Accessibility and accommodations', 'type'=>'textarea'],
            ],
            'Question banks' => [
                'topic' => ['label'=>'Topic / outcome coverage', 'type'=>'textarea', 'required'=>true],
                'types' => ['label'=>'Question types and answer-key policy', 'type'=>'textarea', 'required'=>true],
                'count' => ['label'=>'Question count', 'type'=>'number', 'min'=>1, 'max'=>100000, 'required'=>true],
                'difficulty' => ['label'=>'Difficulty', 'type'=>'select', 'options'=>['beginner','intermediate','advanced','mixed']],
                'randomization' => ['label'=>'Selection and randomization rules', 'type'=>'textarea'],
            ],
            'Instructor review' => [
                'application' => ['label'=>'Application reference', 'type'=>'text', 'required'=>true],
                'qualifications' => ['label'=>'Qualification review summary', 'type'=>'textarea', 'required'=>true],
                'teaching' => ['label'=>'Teaching evidence summary', 'type'=>'textarea'],
                'conflicts' => ['label'=>'Conflict-of-interest declaration', 'type'=>'textarea', 'required'=>true],
                'outcome' => ['review'=>true, 'label'=>'Review recommendation', 'type'=>'select', 'options'=>['pending','request information','recommend approval','recommend rejection']],
            ],
            'Grading and moderation' => [
                'rubric' => ['label'=>'Rubric criteria and weights', 'type'=>'textarea', 'required'=>true],
                'method' => ['label'=>'Grading method', 'type'=>'select', 'options'=>['automatic','manual','hybrid'], 'required'=>true],
                'moderator' => ['label'=>'Moderator', 'type'=>'text', 'required'=>true],
                'moderation' => ['review'=>true, 'label'=>'Moderation and boundary-case findings', 'type'=>'textarea'],
                'appeals' => ['label'=>'Appeal and override handling', 'type'=>'textarea'],
            ],
            'Certificate templates and issuance' => [
                'template' => ['label'=>'Certificate template reference', 'type'=>'text', 'required'=>true],
                'language' => ['label'=>'Language', 'type'=>'text', 'required'=>true],
                'eligibility' => ['label'=>'Eligibility rule', 'type'=>'textarea', 'required'=>true],
                'verification' => ['label'=>'Serial / verification / revocation rules', 'type'=>'textarea', 'required'=>true],
                'issued_count' => ['review'=>true, 'label'=>'Staging certificates issued', 'type'=>'number', 'min'=>0, 'max'=>100000],
                'issuance' => ['review'=>true, 'label'=>'Issuance review', 'type'=>'select', 'options'=>['pending','blocked','accepted']],
            ],
            'Course revision history' => [
                'source_version' => ['label'=>'Previous course version', 'type'=>'text'],
                'outcomes' => ['label'=>'Learning outcomes snapshot', 'type'=>'textarea', 'required'=>true],
                'lessons' => ['label'=>'Lesson structure snapshot', 'type'=>'textarea', 'required'=>true],
                'assessments' => ['label'=>'Assessment mapping snapshot', 'type'=>'textarea', 'required'=>true],
                'eligibility' => ['label'=>'Certificate eligibility snapshot', 'type'=>'textarea'],
                'impact' => ['label'=>'Enrolled-learner / migration impact', 'type'=>'textarea'],
            ],
            'Release readiness' => [
                'scope' => ['label'=>'Release scope and commit reference', 'type'=>'textarea', 'required'=>true],
                'installation' => ['label'=>'Staging installation acceptance', 'type'=>'select', 'options'=>['pending','blocked','complete'], 'gate'=>true, 'review'=>true],
                'permissions' => ['label'=>'Role and permission acceptance', 'type'=>'select', 'options'=>['pending','blocked','complete'], 'gate'=>true, 'review'=>true],
                'backup' => ['label'=>'Backup / restore acceptance', 'type'=>'select', 'options'=>['pending','blocked','complete'], 'gate'=>true, 'review'=>true],
                'devices' => ['label'=>'Browser / device acceptance', 'type'=>'select', 'options'=>['pending','blocked','complete'], 'gate'=>true, 'review'=>true],
            ],
        ];
    }

    public static function fields(string $view, mixed $input): array
    {
        if (!is_array($input)) {
            throw new \InvalidArgumentException('Structured fields must be an object.');
        }
        $schema = self::views()[$view] ?? null;
        if ($schema === null || array_diff(array_keys($input), array_keys($schema))) {
            throw new \InvalidArgumentException('Unknown workspace field or view.');
        }
        $result = [];
        foreach ($schema as $key => $definition) {
            $value = self::text($input, $key, 12000);
            if ($value !== '' && $definition['type'] === 'select' && !in_array($value, $definition['options'], true)) {
                throw new \InvalidArgumentException('Invalid selection: '.$definition['label']);
            }
            if ($value !== '' && $definition['type'] === 'number'
                && (!preg_match('/^\d+$/D', $value) || (float)$value < $definition['min'] || (float)$value > $definition['max'])) {
                throw new \InvalidArgumentException('Invalid number: '.$definition['label']);
            }
            $result[$key] = $value;
        }
        return $result;
    }

    public static function text(array $input, string $key, int $limit = 500): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value) || strlen($value) > $limit || !preg_match('//u', $value)) {
            throw new \InvalidArgumentException('Invalid or oversized field: '.$key);
        }
        return trim($value);
    }
}
