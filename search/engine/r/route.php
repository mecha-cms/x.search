<?php namespace _\lot\x\search;

// Just like the `\k` function but search only in the YAML value
function k(string $f, array $q = []) {
    if (\is_dir($f) && $h = \opendir($f)) {
        while (false !== ($b = \readdir($h))) {
            if ($b !== '.' && $b !== '..') {
                $n = \pathinfo($b, \PATHINFO_FILENAME);
                foreach ($q as $v) {
                    if (empty($v) && $v !== '0') {
                        continue;
                    }
                    $r = $f . DS . $b;
                    // Find by query in file name…
                    if (\stripos($n, $v) !== false) {
                        yield $r;
                    // Find by query in page data…
                    } else if (\is_file($r)) {
                        $content = false;
                        foreach (\stream($r) as $kk => $vv) {
                            // Start of header, skip!
                            if ($kk === 0 && $vv === '---') {
                                continue;
                            }
                            // End of header, now ignore lines that looks like `key: value`
                            if ($vv === '...') {
                                $content = true;
                            }
                            if ($content) {
                                if (\stripos($vv, $v) !== false) {
                                    yield $r;
                                }
                            } else {
                                if (\stripos(\explode(': ', $vv)[1] ?? "", $v) !== false) {
                                    yield $r;
                                }
                            }
                        }
                    }
                }
            }
        }
        \closedir($h);
    }
}

$q = \state('search')['key'];
if ($query = \Get::get($q)) {
    $folder = PAGE . $url->path(DS);
    $file = \File::exist([
        $folder . '.page',
        $folder . '.archive',
    ]);
    if ($file) {
        $search = \array_merge([$query], \preg_split('/\s+/', $query));
        $search = \array_unique($search);
        $files = [];
        foreach (k($folder, $search) as $k => $v) {
            $a = \pathinfo($v);
            if (!empty($a['filename']) && !empty($a['extension']) && $a['extension'] === 'page') {
                $files[] = $v;
            }
        }
        $files = \array_count_values($files);
        \arsort($files);
        $GLOBALS['t'][] = $language->doSearch;
        $GLOBALS['t'][] = \To::title($query);
        \Route::over('*', function() use($file, $files, $query, $url) {
            $files = \array_keys($files);
            $page = new \Page($file);
            $pages = new \Pages($files);
            $chunk = $page['chunk'];
            $i = ($url->i ?: 1) - 1;
            $path = $url->path;
            $pager = new \Pager\Pages($pages->get(), [$chunk, $i], $url . $path);
            $GLOBALS['pager'] = $pager;
            $GLOBALS['pages'] = $pages = $pages->chunk($chunk, $i);
            if ($pages->count() === 0) {
                \Config::set([
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
                $this->content('404' . $path . '/' . ($i + 1));
            }
            require __DIR__ . DS . 'hook.php';
            \Config::set([
                'has' => [
                    'page' => true,
                    'pages' => true
                ],
                'is' => [
                    'page' => false,
                    'pages' => true
                ]
            ]);
            $this->content('pages' . $path . '/' . ($i + 1));
        });
    }
}