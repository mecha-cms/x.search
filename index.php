<?php namespace x\search;

// Just like the `\k` function but search only in the YAML value
function k(string $f, array $q = []) {
    if (\is_dir($f) && $h = \opendir($f)) {
        while (false !== ($b = \readdir($h))) {
            if ('.' !== $b && '..' !== $b) {
                $n = \pathinfo($b, \PATHINFO_FILENAME);
                foreach ($q as $v) {
                    if (empty($v) && '0' !== $v) {
                        continue;
                    }
                    $r = $f . \D . $b;
                    // Find by query in file name…
                    if (false !== \stripos($n, $v)) {
                        yield $r => \is_dir($r) ? 0 : 1;
                    // Find by query in page data…
                    } else if (\is_file($r)) {
                        $content = false;
                        $start = \defined("\\YAML\\SOH") ? \YAML\SOH : '---';
                        $end = \defined("\\YAML\\EOT") ? \YAML\EOT : '...';
                        foreach (\stream($r) as $kk => $vv) {
                            // Start of header, skip!
                            if (0 === $kk && $start . "\n" === $vv) {
                                continue;
                            }
                            // End of header, now ignore any line(s) that looks like `key: value`
                            if ($end . "\n" === $vv) {
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
}

function mark($content) {
    $key = \State::get('x.search.key') ?? 0;
    if (0 !== $key && $query = \preg_split('/\s+/', (string) \get($_GET, $key))) {
        $parts = \preg_split('/(<[^>]+>)/', $content, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);
        $out = "";
        foreach ($parts as $v) {
            if ($v && '<' === $v[0] && '>' === \substr($v, -1)) {
                $out .= $v; // Ignore HTML tag(s)
            } else {
                foreach ($query as $q) {
                    if (false === \stripos($v, $q)) {
                        continue;
                    }
                    $v = \preg_replace('/' . \x($q) . '/i', '<mark>$0</mark>', $v);
                }
                $out .= $v;
            }
        }
        return "" !== $out ? $out : null;
    }
    return $content;
}

$key = \State::get('x.search.key') ?? 0;
$path = $url->path;
if (0 !== $key && null !== ($query = \get($_GET, $key))) {
    $folder = \LOT . \D . 'page' . \strtr($path, '/', \D);
    if ($file = \exist([
        $folder . '.archive',
        $folder . '.page'
    ], 1)) {
        $search = \array_merge([$query], \preg_split('/\s+/', $query));
        $search = \array_unique($search);
        $files = [];
        foreach (k($folder, $search) as $k => $v) {
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
        $files = \array_keys($files);
        \Hook::set('route.search', function ($content, $path, $query, $hash, $r) use ($file, $files, $url) {
            if (null !== $content) {
                return $content;
            }
            if ($path && \preg_match('/^(.*?)\/([1-9]\d*)$/', $path, $m)) {
                [$any, $path, $i] = $m;
            }
            $page = new \Page($file);
            $pages = new \Pages($files);
            $chunk = $page['chunk'];
            $i = ((int) ($i ?? 1)) - 1;
            $pager = new \Pager\Pages($pages->get(), [$chunk, $i], $url . '/' . $path);
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
                $GLOBALS['t'][] = \i('Error');
                return ['page', [], 404];
            }
            // Apply the hook only if there is a match
            \Hook::set([
                'page.content',
                'page.description',
                'page.title'
            ], __NAMESPACE__ . "\\mark", 2.1);
            \State::set([
                'chunk' => $chunk,
                'deep' => 0,
                'has' => [
                    'page' => true,
                    'pages' => true,
                    'query' => true
                ],
                'is' => [
                    'page' => false,
                    'pages' => true
                ]
            ]);
            $GLOBALS['t'][] = i('Search');
            $GLOBALS['t'][] = '&#x201C;' . $r['query'] . '&#x201D;';
            return ['pages', [], 200];
        }, 100);
    } else {
        \Hook::set('route.search', function ($content, $path, $query, $hash, $r) {
            if (null !== $content) {
                return $content;
            }
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
            $GLOBALS['t'][] = \i('Error');
            return ['page', [], 404];
        }, 100);
    }
    if ("" !== ($q = (string) $query)) {
        \Hook::set('route.page', function ($content, $path, $query, $hash) use ($q) {
            $r['query'] = $q;
            return \Hook::fire('route.search', [$content, $path, $query, $hash, $r]);
        }, 90);
    }
}

if (\class_exists("\\Layout")) {
    !\Layout::path('form/search') && \Layout::set('form/search', __DIR__ . \D . 'engine' . \D . 'y' . \D . 'form' . \D . 'search.php');
}