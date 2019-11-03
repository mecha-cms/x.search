<?php namespace _\lot\x\search;

// Just like the `\k` function but search only in the YAML value
$search = function(string $f, array $q = []) {
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
                        foreach (\stream($r) as $kk => $vv) {
                            // Start of header, skip!
                            if (0 === $kk && '---' === $vv) {
                                continue;
                            }
                            // End of header, now ignore any line(s) that looks like `key: value`
                            if ('...' === $vv) {
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

$q = \State::get('x.search.key');
if ($query = \Get::get($q)) {
    $folder = \PAGE . $url->path(\DS);
    $file = \File::exist([
        $folder . '.page',
        $folder . '.archive',
    ]);
    if ($file) {
        $search = \array_merge([$query], \preg_split('/\s+/', $query));
        $search = \array_unique($search);
        $files = [];
        foreach ($search($folder, $search) as $k => $v) {
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
        \Route::over('*', function() use($file, $files, $url) {
            $files = \array_keys($files);
            $page = new \Page($file);
            $pages = new \Pages($files);
            $chunk = $page['chunk'];
            $i = ($url['i'] ?? 1) - 1;
            $path = $url->path;
            $pager = new \Pager\Pages($pages->get(), [$chunk, $i], $url . $path);
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
                $this->status(404);
                $this->view('404' . $path . '/' . ($i + 1));
            }
            // Apply the hook only if there is a match
            require __DIR__ . \DS . 'hook.php';
            \State::set([
                'has' => [
                    'page' => true,
                    'pages' => true
                ],
                'is' => [
                    'page' => false,
                    'pages' => true
                ]
            ]);
            $this->view('pages' . $path . '/' . ($i + 1));
        });
    } else {
        \Route::over('*', function() use($url) {
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
            // TODO: Use forbidden status code
            $this->status(404);
            $this->layout('404' . $url->path);
        });
    }
    \State::set('is.search', true);
}