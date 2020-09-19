<?php namespace _\lot\x\search;

// Just like the `\k` function but search only in the YAML value
$get = function(string $f, array $q = []) {
    if (\is_dir($f) && $h = \opendir($f)) {
        while (false !== ($b = \readdir($h))) {
            if ('.' !== $b && '..' !== $b) {
                $n = \pathinfo($b, \PATHINFO_FILENAME);
                foreach ($q as $v) {
                    if (empty($v) && '0' !== $v) {
                        continue;
                    }
                    $r = $f . \DS . $b;
                    // Find by query in file name…
                    if (false !== \stripos($n, $v)) {
                        yield $r => \is_dir($r) ? 0 : 1;
                    // Find by query in page data…
                    } else if (\is_file($r)) {
                        $content = false;
                        $soh = \defined("\\YAML\\SOH") ? \YAML\SOH : '---';
                        $eot = \defined("\\YAML\\EOT") ? \YAML\EOT : '...';
                        foreach (\stream($r) as $kk => $vv) {
                            // Start of header, skip!
                            if (0 === $kk && $soh . "\n" === $vv) {
                                continue;
                            }
                            // End of header, now ignore any line(s) that looks like `key: value`
                            if ($eot . "\n" === $vv) {
                                $content = true;
                            }
                            if ($content) {
                                if (false !== \stripos($vv, $v)) {
                                    yield $r => 1;
                                }
                            } else {
                                if (false !== \stripos(\explode(': ', $vv)[1] ?? "", $v)) {
                                    yield $r => 1;
                                }
                            }
                        }
                    }
                }
            }
        }
        \closedir($h);
    }
};

$key = \State::get('x.search.key') ?? 0;
if (0 !== $key && $query = \Get::get($key)) {
    $folder = \LOT . \DS . 'page' . $url->path(\DS);
    $file = \File::exist([
        $folder . '.page',
        $folder . '.archive',
    ]);
    if ($file) {
        $search = \array_merge([$query], \preg_split('/\s+/', $query));
        $search = \array_unique($search);
        $files = [];
        foreach ($get($folder, $search) as $k => $v) {
            if (0 === $v) {
                continue;
            }
            $a = \pathinfo($k);
            if (!empty($a['filename']) && !empty($a['extension']) && 'page' === $a['extension']) {
                $files[] = $k;
            }
        }
        // Check how much duplicate path captured in `$files` after doing the search
        $files = \array_count_values($files);
        // Then sort them reversed to put the most captured item(s) on top
        \arsort($files);
        $GLOBALS['t'][] = i('Search');
        $GLOBALS['t'][] = '&#x201C;' . $query . '&#x201D;';
        \Route::hit('*', function() use($file, $files, $url) {
            $files = \array_keys($files);
            $page = new \Page($file);
            $pages = new \Pages($files);
            $chunk = $page['chunk'];
            $i = ($url['i'] ?? 1) - 1;
            $path = $url->path;
            $pager = new \Pager\Pages($pages->get(), [$chunk, $i], $url . $path);
            $GLOBALS['page'] = $page;
            $GLOBALS['pager'] = $pager;
            $GLOBALS['pages'] = $pages = $pages->chunk($chunk, $i);
            if (0 === $pages->count()) {
                \State::set([
                    'has' => [
                        'page' => false,
                        'pages' => false
                    ],
                    'is' => [
                        'error' => 404,
                        'page' => true,
                        'pages' => false
                    ]
                ]);
                $this->layout('404' . $path . '/' . ($i + 1));
            }
            // Apply the hook only if there is a match
            require __DIR__ . \DS . 'hook.php';
            \State::set([
                'chunk' => $chunk,
                'deep' => 0,
                'has' => [
                    'page' => true,
                    'pages' => true
                ],
                'is' => [
                    'page' => false,
                    'pages' => true
                ]
            ]);
            $this->layout('pages' . $path . '/' . ($i + 1));
        });
    } else {
        \Route::hit('*', function() use($url) {
            \State::set([
                'has' => [
                    'page' => false,
                    'pages' => false
                ],
                'is' => [
                    'error' => 404,
                    'page' => true,
                    'pages' => false
                ]
            ]);
            $this->layout('404' . $url->path);
        });
    }
    \State::set('is.search', true);
}
