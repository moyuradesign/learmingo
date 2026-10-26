<?php
declare(strict_types=1);

namespace Learmingo\Domain;

final class CoursePolicy
{
    public static function canAuthor(array $course, int $actorId): bool
    {
        return $actorId > 0 && (int) ($course['instructor_id'] ?? 0) === $actorId;
    }

    public static function canEdit(array $course, int $actorId): bool
    {
        return self::canAuthor($course, $actorId) && ($course['status'] ?? '') === 'draft';
    }

    public static function canSubmit(array $course, int $actorId, int $lessonCount): bool
    {
        return self::canEdit($course, $actorId) && $lessonCount > 0;
    }

    public static function canReview(array $course): bool
    {
        return ($course['status'] ?? '') === 'pending';
    }

    public static function validTitle(string $title): bool
    {
        $title = trim($title);
        return $title !== '' && strlen($title) <= 200;
    }

    public static function validVideoUrl(string $url): bool
    {
        if ($url === '') {
            return true;
        }
        if (strlen($url) > 500 || preg_match('/[\x00-\x1f\x7f]/', $url)) {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
