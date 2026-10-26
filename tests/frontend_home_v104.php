<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
require dirname(__DIR__).'/app/Helpers/functions.php';
use Learmingo\Services\CataloguePreview;
use Learmingo\Services\FrontendSettings;

$courses=CataloguePreview::all();
$categories=CataloguePreview::categories();
$previewMode=true;
if(count($courses)!==10 || count($categories)<8)throw new RuntimeException('Demo catalogue source missing.');
$seen=[];
foreach($courses as $c){
    if(isset($seen[$c['slug']]))throw new RuntimeException('Duplicate demo slug.');
    $seen[$c['slug']]=true;
    if(!CataloguePreview::image($c['slug']))throw new RuntimeException('Missing demo cover.');
}
$hero=dirname(__DIR__).'/app/Services/learmingo-hero-v104.webp';
if(!is_file($hero) || file_get_contents($hero,false,null,8,4)!=='WEBP')
    throw new RuntimeException('Hero image invalid.');
$css=file_get_contents(dirname(__DIR__).'/app/Services/learmingo-ui-v102.css');
if(!str_contains($css,'LEARMINGO_COURSE_FIRST_HOME_V104') || !str_contains($css,'repeat(5,minmax(0,1fr))'))
    throw new RuntimeException('Responsive homepage stylesheet missing.');
ob_start();
require dirname(__DIR__).'/resources/views/home.php';
$html=(string)ob_get_clean();
if(substr_count($html,'class="lm4-course-card"')!==10 ||
    substr_count($html,'class="lm4-category-card"')<8 ||
    !str_contains($html,'/learmingo-hero-art?v=1.0.4') ||
    str_contains($html,'lm2-study-card') ||
    str_contains($html,'lm2-values'))
    throw new RuntimeException('Homepage sections do not match course-first layout.');
$routes=file_get_contents(dirname(__DIR__).'/routes/web.php');
if(!str_contains($routes,"require __DIR__ . '/frontend.php'"))
    throw new RuntimeException('Frontend adapter not included.');
$router=new Learmingo\Core\Router();
$render=static function(string $view,array $data=[]):void{};
require dirname(__DIR__).'/routes/frontend.php';
$property=new ReflectionProperty(Learmingo\Core\Router::class,'routes');
$registered=$property->getValue($router);
foreach(['/','/courses','/categories','/learmingo-hero-art','/learmingo-course-cover','/learmingo-ui/style','/learmingo-ui/script','/dashboard/appearance'] as $uri){
    if(!isset($registered['GET'][$uri]))throw new RuntimeException('Missing GET route '.$uri);
}
if(!isset($registered['POST']['/dashboard/appearance']))
    throw new RuntimeException('Missing settings save route.');
echo "PASS  Course-first hero, ten covers, categories, original permissions and route integration\n";
