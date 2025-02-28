<?php namespace x\search;

function page__($content) {
    if (!$content || !\is_string($content)) {
        return $content;
    }
    if ("" === ($search = \State::get('[x].query.search'))) {
        return $content;
    }
    $query = \explode(' ', $search);
    $strict = $search !== \strtolower($search); // Search query is case sensitive?
    // Prioritize the longest query string when marking up the text…
    \usort($query, static function ($a, $b) {
        return \strlen($b) <=> \strlen($a);
    });
    $r = "";
    foreach ($query as &$q) {
        $q = \x($q);
    }
    unset($q);
    $query = \implode('|', $query);
    foreach (\apart($content, ['script', 'style', 'textarea']) as $v) {
        if (0 === $v[1]) {
            $r .= \preg_replace('/' . $query . '/' . ($strict ? "" : 'i'), '<mark tabindex="0">' . \S . '$0' . \S . '</mark>', $v[0]);
            continue;
        }
        $r .= $v[0];
    }
    return $r;
}

function page__search_score($n) {
    if (null !== $n) {
        return $n;
    }
    \extract(\lot(), \EXTR_SKIP);
    $n = 0;
    if ($score = \array_filter((array) ($state->x->search->score ?? []))) {
        foreach ($score as $k => $v) {
            $test = \s($this->{$k} ?? $this[$k] ?? "");
            if (\is_string($test) && "" !== $test) {
                $n += \substr_count($test, \S . '</mark>');
            }
        }
    }
    return $n;
}

// This route is executed after the default page route. It will alter the value of the current `$pages` variable.
function route__page($content, $path, $query, $hash) {
    \extract(\lot(), \EXTR_SKIP);
    $path = \trim($path ?? "", '/');
    $route = \trim($state->x->search->route ?? 'search', '/');
    if ($part = \x\page\n($path)) {
        $path = \substr($path, 0, -\strlen('/' . $part));
    }
    $part = ($part ?? 0) - 1;
    $chunk = $state->x->search->chunk ?? 5;
    $deep = $state->x->search->deep ?? true;
    $sort = \array_replace([-1, 'search-score'], (array) ($state->x->search->sort ?? []));
    $search = \State::get('[x].query.search') ?? "";
    // For `/search/…`
    if ($path && 0 === \strpos($path . '/', $route . '/')) {
        $r = \substr($path, \strlen($route) + 1);
        $folder = \LOT . \D . 'page' . ("" !== $r ? \D . $r : "");
        if ("" !== $r && ($file = \exist([
            $folder . '.archive',
            $folder . '.page'
        ], 1))) {
            $page = new \Page($file);
            // Create a new batch of `$pages`
            $pages = $page->children('page', $deep) ?? new \Pages;
        } else {
            $page = \Page::from([
                'description' => \i('Search results for query %s.', '&#x201c;' . \strip_tags($search) . '&#x201d;'),
                'exist' => true, // Make it to look like a page that does exist!
                'title' => \i('Search'),
                'type' => 'HTML'
            ]);
            // Create a new batch of `$pages`
            $pages = \Pages::from($folder, 'page', $deep = true);
        }
        $path = $r;
    // Take the `$pages` value from its parent batch…
    } else if (isset($pages) && \is_object($pages) && $pages instanceof \Pages && $pages->parent) {
        $pages = $pages->parent;
    }
    \State::set([
        'chunk' => $chunk,
        'deep' => $deep,
        'part' => $part + 1,
        'sort' => $sort
    ]);
    $score = \array_filter((array) ($state->x->search->score ?? []));
    $strict = $search !== \strtolower($search); // Search query is case sensitive?
    \asort($score);
    $pages = $pages->is(function ($page) use ($score, $search, $strict) {
        foreach (\explode(' ', $search) as $v) {
            foreach ($score as $kk => $vv) {
                $test = \s($page->{$kk} ?? $page[$kk] ?? "");
                if (\is_string($test) && "" !== $test && false !== ($strict ? \strpos($test, $v) : \stripos($test, $v))) {
                    return true;
                }
            }
        }
        return false;
    })->sort($sort);
    $pager = \Pager::from($pages);
    $pager->hash = $hash;
    $pager->path = '/' . ("" !== $path ? $path : $route);
    $pager->query = $query;
    if (isset($t) && \i('Error') === $t->last) {
        $t->last(true); // Remove the “Error” title
    }
    $t[] = \i('Search'); // Add the “Search” title
    \lot('page', $page);
    \lot('pager', $pager->chunk($chunk, $part));
    \lot('pages', $pages->chunk($chunk, $part));
    \lot('t', $t);
    \State::set([
        'chunk' => $chunk,
        'count' => $pages->count,
        'deep' => $deep,
        'has' => [
            'next' => !!$pager->next,
            'page' => false,
            'pages' => true,
            'part' => $part >= 0,
            'prev' => !!$pager->prev
        ],
        'is' => [
            'error' => false,
            'page' => false,
            'pages' => true
        ],
        'part' => $part + 1
    ]);
    return \Hook::fire('route.search', [$content, ("" !== $path ? '/' . $path : "") . '/' . ($part + 1), $query, $hash]);
}

// Based on the condition of the top route, at this point there should have been a search query set properly and a page
// part present in the URL.
function route__search($content, $path, $query, $hash) {
    \extract(\lot(), \EXTR_SKIP);
    if (0 === $pages->count) {
        \State::set([
            'has' => [
                'next' => false,
                'page' => false,
                'pages' => false,
                'parent' => false,
                'prev' => false
            ],
            'is' => [
                'error' => 404,
                'page' => false,
                'pages' => true
            ]
        ]);
        \lot('t')[] = \i('Error');
        return ['pages/search', [], 404];
    }
    \Hook::set('page.search-score', __NAMESPACE__ . "\\page__search_score", 0);
    return ['pages/search', [], 200];
}

if (\class_exists("\\Layout") && !\Layout::of('form/search')) {
    \Layout::set('form/search', static function (string $key, array $lot = []) {
        \extract(\lot($lot), \EXTR_SKIP);
        $key = $lot['key'] ?? null;
        $has_key = isset($key) && \is_string($key);
        $has_path = !empty($url->path);
        $has_route = isset($route) && \is_string($route);
        $is_page = $site->is('page');
        $current = $has_path ? $url->current(false, false) : null;
        $key = $has_key ? $key : ($state->x->search->key ?? null);
        $title = $is_page ? ($page->parent ? ($page->parent->title ?? null) : null) : ($page->title ?? null);
        return new \HTML(\Hook::fire('y.form.search', [[
            0 => 'form',
            1 => [
                'search' => [
                    0 => 'p',
                    1 => [
                        'input' => [
                            0 => 'input',
                            1 => false,
                            2 => [
                                'name' => null !== $key ? (false !== \strpos(\strtr($key, ["\\." => \P]), '.') ? (static function ($key) {
                                    // Convert `foo.bar.baz` to `foo[bar][baz]`
                                    $keys = \explode('.', \strtr($key, ["\\." => \P]));
                                    $v = \strtr(\array_shift($keys), [\P => '.']);
                                    while (\is_string($key = \array_shift($keys))) {
                                        $v .= '[' . \strtr($key, [\P => '.']) . ']';
                                    }
                                    return $v;
                                })($key) : \strtr($key, ["\\." => '.'])) : null,
                                'placeholder' => $title && !$has_route ? \i('Search in %s', ['&#x201c;' . \S . $title . \S . '&#x201d;']) : null,
                                'type' => 'text',
                                'value' => null !== $key ? \get($_GET, $key) : null
                            ]
                        ],
                        ' ' => ' ',
                        'button' => [
                            0 => 'button',
                            1 => \i('Search'),
                            2 => ['type' => 'submit']
                        ]
                    ]
                ]
            ],
            2 => [
                'action' => $has_route ? ($url . '/' . \trim($route, '/') . '/1') : ($has_path && $is_page ? \dirname($current) . '/1' : null),
                'method' => 'get',
                'name' => 'search'
            ]
        ]], $page), true);
    });
}

if (null !== ($search = \get($_GET, $state->x->search->key ?? 'query'))) {
    if ("" !== ($search = \trim(\preg_replace('/\s+/', ' ', $search)))) {
        \State::set('[x].query.search', $search);
        \State::set('has.query', true);
        if ($score = (array) ($state->x->search->score ?? [])) {
            foreach ($score as $k => $v) {
                \Hook::set('page.' . $k, __NAMESPACE__ . "\\page__", 2.1);
            }
        }
    }
    if (\x\page\n(\trim($url->path ?? "", '/'))) {
        \Hook::set('route.page', __NAMESPACE__ . "\\route__page", 100.1);
        \Hook::set('route.search', __NAMESPACE__ . "\\route__search", 100);
    }
}