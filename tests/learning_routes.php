<?php
declare(strict_types=1);
/** Regression for route table ownership and accidental replacement by learner progress. */
require dirname(__DIR__).'/bootstrap/autoload.php';

$router = new \Learmingo\Core\Router();
$requireRole = static fn (string ...$roles): array => ['id'=>1,'role'=>'admin'];
$render = static function (string $view, array $data = []): void {};
$input = static fn (string $key, int $length=4096): string => '';
$query = static fn (string $key, int $length=250): string => '';
require dirname(__DIR__).'/routes/learning.php';

$property=new ReflectionProperty(\Learmingo\Core\Router::class,'routes');
$actual=$property->getValue($router);
foreach ([
    'GET' => ['/instructor/course','/admin/courses','/admin/audit'],
    'POST' => [
        '/instructor/sections/store','/instructor/lessons/store',
        '/instructor/lessons/update','/instructor/courses/submit',
        '/admin/courses/review','/learn/complete',
        '/instructor/curriculum/change',
    ],
] as $method=>$paths) {
    foreach ($paths as $path) {
        if (!isset($actual[$method][$path]) || !is_callable($actual[$method][$path])) {
            throw new RuntimeException("Missing $method $path");
        }
    }
}
$routeSource=static function (Closure $callback): string {
    $ref=new ReflectionFunction($callback);
    $lines=file($ref->getFileName());
    if (!is_array($lines)) { throw new RuntimeException('Route source unavailable'); }
    return implode('',array_slice($lines,$ref->getStartLine()-1,
        $ref->getEndLine()-$ref->getStartLine()+1));
};
$editor=$routeSource($actual['POST']['/instructor/lessons/update']);
if (!str_contains($editor,'->editLesson(')
    || str_contains($editor,'->completeLesson(')
    || str_contains($editor,'->completeVersionLesson(')
    || !str_contains($editor,'/instructor/course?course=')) {
    throw new RuntimeException('Instructor lesson update incorrectly invokes student progress.');
}
$completion=$routeSource($actual['POST']['/learn/complete']);
if (!str_contains($completion,'->completeLesson(')
    || !str_contains($completion,'->completeVersionLesson(')
    || !str_contains($completion,'(int)$student')) {
    throw new RuntimeException('Student completion route is not version-aware.');
}
$review=$routeSource($actual['POST']['/admin/courses/review']);
if (!str_contains($review, "\$requireRole('admin')") || !str_contains($review,'->review(')) {
    throw new RuntimeException('Admin review role guard missing.');
}
echo "PASS  Learning routes registered and instructor/student actions remain separated.\n";
