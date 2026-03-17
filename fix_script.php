<?php
$files = [
    'studio_users/script.js',
    'similar_dashboard.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    // Let's first dump the match to see what it is
    preg_match_all('/\/\/ Office geo-fence coordinates(.*?)(?=\/\/ Log for debugging)/s', $content, $matches);
    print_r($matches[0]);
}
