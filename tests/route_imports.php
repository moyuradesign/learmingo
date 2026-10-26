<?php
declare(strict_types=1);

/**
 * File-level import regression: PHP use declarations do not propagate from
 * routes/web.php into required route adapters.
 */
$root=dirname(__DIR__);
$required=['Database','Csrf','Response'];
foreach (['routes/learning.php','routes/milestone3.php'] as $relative) {
    $source=file_get_contents($root.'/'.$relative);
    if (!is_string($source)) { throw new RuntimeException('Route adapter unavailable.'); }
    foreach ($required as $class) {
        $uses=(bool)preg_match('/^use Learmingo\\\\Core\\\\'.preg_quote($class,'/').';$/m',$source);
        $references=(bool)preg_match('/(?<![A-Za-z0-9_\\\\])'.preg_quote($class,'/').'::/',$source);
        if (!$uses || !$references) {
            throw new RuntimeException('Missing import/reference: '.$relative.' :: '.$class);
        }
    }
}
echo "PASS  Route adapters resolve all core classes in file scope.\n";
