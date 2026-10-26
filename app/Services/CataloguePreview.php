<?php
declare(strict_types=1);
namespace Learmingo\Services;

/** Demonstration content. This is not an enrolment or database seeder. */
final class CataloguePreview
{
    public static function all(): array
    {
        return [['slug' => 'lm-preview-web-development', 'title' => 'Modern Web Development', 'category_name' => 'Web Development', 'category_slug' => 'web-development', 'instructor' => 'Alex Morgan', 'description' => 'Build responsive websites with HTML, CSS, JavaScript and practical projects.', 'price' => 0.0, 'level' => 'Beginner', 'lessons' => '32 lessons', 'duration' => '8h 20m', 'is_preview' => true], ['slug' => 'lm-preview-ui-ux-design', 'title' => 'UI/UX Design Fundamentals', 'category_name' => 'Design', 'category_slug' => 'design', 'instructor' => 'Sophie Chen', 'description' => 'Understand user journeys, wireframes, design systems and accessible interfaces.', 'price' => 49.0, 'level' => 'Beginner', 'lessons' => '28 lessons', 'duration' => '6h 15m', 'is_preview' => true], ['slug' => 'lm-preview-digital-marketing', 'title' => 'Digital Marketing Strategy', 'category_name' => 'Business', 'category_slug' => 'business', 'instructor' => 'Daniel Lee', 'description' => 'Plan content campaigns, interpret analytics and grow a compelling online presence.', 'price' => 39.0, 'level' => 'Intermediate', 'lessons' => '24 lessons', 'duration' => '5h 45m', 'is_preview' => true], ['slug' => 'lm-preview-python-data', 'title' => 'Python for Data Science', 'category_name' => 'Data Science', 'category_slug' => 'data-science', 'instructor' => 'Maya Patel', 'description' => 'Use Python to explore data, visualize trends and solve real business questions.', 'price' => 59.0, 'level' => 'Intermediate', 'lessons' => '36 lessons', 'duration' => '9h 40m', 'is_preview' => true], ['slug' => 'lm-preview-mobile-apps', 'title' => 'Mobile App Design', 'category_name' => 'Mobile Development', 'category_slug' => 'mobile-development', 'instructor' => 'James Wilson', 'description' => 'Prototype elegant mobile experiences and learn the basics of app workflows.', 'price' => 45.0, 'level' => 'Beginner', 'lessons' => '26 lessons', 'duration' => '6h 30m', 'is_preview' => true], ['slug' => 'lm-preview-cloud-computing', 'title' => 'Cloud Computing Essentials', 'category_name' => 'Technology', 'category_slug' => 'technology', 'instructor' => 'Olivia Martin', 'description' => 'Learn cloud architecture, deployment fundamentals and practical scalability.', 'price' => 55.0, 'level' => 'Intermediate', 'lessons' => '30 lessons', 'duration' => '7h 50m', 'is_preview' => true], ['slug' => 'lm-preview-project-management', 'title' => 'Project Management Basics', 'category_name' => 'Business', 'category_slug' => 'business', 'instructor' => 'Noah Rivera', 'description' => 'Organize milestones, communicate effectively and deliver projects with confidence.', 'price' => 35.0, 'level' => 'Beginner', 'lessons' => '20 lessons', 'duration' => '4h 50m', 'is_preview' => true], ['slug' => 'lm-preview-productivity', 'title' => 'Productivity & Time Management', 'category_name' => 'Personal Development', 'category_slug' => 'personal-development', 'instructor' => 'Emma Brooks', 'description' => 'Create sustainable habits, prioritize what matters and get focused work done.', 'price' => 29.0, 'level' => 'Beginner', 'lessons' => '18 lessons', 'duration' => '4h 10m', 'is_preview' => true], ['slug' => 'lm-preview-english', 'title' => 'English for Everyday Life', 'category_name' => 'Languages', 'category_slug' => 'languages', 'instructor' => 'Lina Roberts', 'description' => 'Build everyday speaking confidence through conversations, vocabulary and practice.', 'price' => 25.0, 'level' => 'All Levels', 'lessons' => '30 lessons', 'duration' => '6h 00m', 'is_preview' => true], ['slug' => 'lm-preview-photography', 'title' => 'Photography for Beginners', 'category_name' => 'Creative Arts', 'category_slug' => 'creative-arts', 'instructor' => 'Ethan Park', 'description' => 'Master composition, exposure, lighting and creative storytelling with your camera.', 'price' => 39.0, 'level' => 'Beginner', 'lessons' => '22 lessons', 'duration' => '5h 20m', 'is_preview' => true]];
    }
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $course) {
            if ($course['slug'] === $slug) { return $course; }
        }
        return null;
    }
    public static function categories(): array
    {
        $categories=[];
        foreach (self::all() as $course) {
            $slug=$course['category_slug'];
            if (!isset($categories[$slug])) {
                $categories[$slug]=['name'=>$course['category_name'],'slug'=>$slug,'course_count'=>0,'total'=>0];
            }
            ++$categories[$slug]['course_count'];++$categories[$slug]['total'];
        }
        return array_values($categories);
    }
    public static function filter(string $search='', string $category=''): array
    {
        return array_values(array_filter(self::all(), static function(array $course) use($search,$category):bool {
            return ($category==='' || $category===$course['category_slug'])
                && ($search==='' || stripos($course['title'].' '.$course['description'].' '.$course['category_name'],$search)!==false);
        }));
    }
    public static function image(string $slug): ?string
    {
        if (self::find($slug) === null) { return null; }
        $path=__DIR__.'/CoursePreviewCovers/'.$slug.'.svg';
        return is_file($path) && !is_link($path) ? $path : null;
    }
}
