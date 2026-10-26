<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use Learmingo\Domain\CoursePolicy;

$assert = static function (bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, "LEARNING POLICY FAILED: " . $message . PHP_EOL);
        exit(1);
    }
};

$course = ['instructor_id' => 12, 'status' => 'draft'];
$assert(CoursePolicy::canAuthor($course, 12), 'owner can author');
$assert(!CoursePolicy::canAuthor($course, 11), 'cross-tenant ownership');
$assert(!CoursePolicy::canAuthor($course, 0), 'no anonymous authoring');
$assert(CoursePolicy::canEdit($course, 12), 'draft may be edited');
$assert(!CoursePolicy::canEdit($course, 11), 'other instructor blocked');
$assert(!CoursePolicy::canSubmit($course, 12, 0), 'empty curriculum blocked');
$assert(CoursePolicy::canSubmit($course, 12, 1), 'nonempty draft may be submitted');
foreach (['pending', 'published', 'archived'] as $status) {
    $course['status'] = $status;
    $assert(!CoursePolicy::canEdit($course, 12), 'submitted/published content locked');
    $assert(!CoursePolicy::canSubmit($course, 12, 1), 'non-draft submission blocked');
}
$assert(CoursePolicy::canReview($course = ['status' => 'pending']), 'pending review');
$assert(!CoursePolicy::canReview(['status' => 'published']), 'published not rereviewed');
$assert(CoursePolicy::validTitle(' Lesson One '), 'title accepts normal text');
$assert(!CoursePolicy::validTitle('   '), 'blank title denied');
$assert(!CoursePolicy::validTitle(str_repeat('a', 201)), 'long title denied');
$assert(CoursePolicy::validVideoUrl(''), 'optional video URL');
$assert(CoursePolicy::validVideoUrl('https://example.org/lecture?v=1'), 'https video');
$assert(!CoursePolicy::validVideoUrl('javascript:alert(1)'), 'script URL denied');
$assert(!CoursePolicy::validVideoUrl('file:///etc/passwd'), 'file URL denied');
$assert(!CoursePolicy::validVideoUrl("https://example.org\r\nLocation:evil"), 'header injection denied');
echo "Learmingo course workflow policy checks passed.\n";
