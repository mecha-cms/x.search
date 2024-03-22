<?php namespace x\search;

function page__content($content) {
    if (!$content || !\is_string($content)) {
        return $content;
    }
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

function page__description($description) {
    return \fire(__NAMESPACE__ . "\\page__content", [$description], $this);
}

function page__title($title) {
    return \fire(__NAMESPACE__ . "\\page__content", [$title], $this);
}

function route__page($content, $path, $query, $hash) {
    $key = \State::get('x.search.key') ?? 0;
    $path = \trim($path ?? "", '/');
    $route = \trim(\State::get('x.search.route') ?? 'search', '/');
    if ($path && 0 === \strpos($path . '/', $route . '/') && 0 !== $key && null !== \get($_GET, $key)) {
        \extract(\lot(), \EXTR_SKIP);
        $r = \substr($path, \strlen($route) + 1);
        $folder = \rtrim(\LOT . \D . 'page' . \D . \strtr($r, '/', \D), \D);
        $folder_home = \LOT . \D . 'page' . \D . \strtr(\trim($state->route ?? 'index', '/'), '/', \D);
        if (\preg_match('/^(.*?)\/([1-9]\d*)$/', $path, $m)) {
            [$any, $path, $part] = $m;
            if (\exist([
                $folder . '.archive',
                $folder . '.page'
            ], 1)) {
                $path .= '/' . $part;
                unset($part);
            } else {
                $folder = \dirname($folder);
            }
        }
        $part = ((int) ($part ?? 1)) - 1;
        $chunk = $state->chunk ?? $state->x->search->chunk ?? 5;
        $deep = $state->deep ?? $state->x->search->deep ?? true;
        $sort = $state->sort ?? $state->x->search->sort ?? [1, 'path'];
        if (\exist([
            $folder . \D . $route . '.archive',
            $folder . \D . $route . '.page'
        ], 1)) {
            return \Hook::fire('route.search', [$content, "" !== $r ? '/' . $r : null, $query, $hash]);
        }
        $page = \Page::from([
            'description' => \i('Search results.'),
            'path' => \exist([
                $folder_home . '.archive',
                $folder_home . '.page'
            ], 1),
            'title' => \i('Search'),
            'x' => 'archive'
        ]);
        if (!$page->exist) {
            return $content;
        }
        if (isset($t) && $t instanceof \Anemone && $t->count > 1)  {
            if (\i('Error') === $t->last) {
                \lot('t')->last(true); // Remove “Error” title from the previous `route` hook(s) if any
            }
        }
        $pages = \Pages::from($folder, 'page', $deep)->sort($sort);
        \lot('page', $page);
        \State::set('count', $count = $page->count); // Total number of page(s) before chunk
        if (0 === $count) {
            return $content;
        }
        \lot('pages', $pages = $pages->chunk($chunk, $part));
        $count = $pages->count; // Total number of page(s) after chunk
        \State::set([
            'chunk' => $chunk,
            'deep' => $deep,
            'has' => [
                'page' => false,
                'pages' => true,
                'parent' => false,
                'part' => !!($part + 1)
            ],
            'is' => [
                'error' => $count ? false : 404,
                'page' => false,
                'pages' => true
            ],
            'part' => $part + 1,
            'sort' => $sort
        ]);
        $path = $r;
    }
    return \Hook::fire('route.search', [$content, "" !== $path ? '/' . $path : null, $query, $hash]);
}

function route__search($content, $path, $query, $hash) {
    $key = \State::get('x.search.key') ?? 0;
    $path = \trim(\preg_replace('/\/[1-9]\d*$/', "", $path ?? ""), '/');
    $route = \trim(\State::get('x.search.route') ?? 'search', '/');
    if (0 !== $key && null !== ($search = \get($_GET, $key))) {
        \extract(\lot(), \EXTR_SKIP);
        \State::set('has.query', true);
        if (isset($page) && $page->exist && isset($pages) && $pages->count) {
            $chunk = $state->chunk ?? $page->chunk ?? 5;
            $part = ($state->part ?? 1) - 1;
            $level = \array_filter((array) ($state->x->search->level ?? []));
            \asort($level);
            $pages = $pages->parent->is(function ($v) use ($level, $search) {
                foreach (\preg_split('/\s+/', $search, -1, \PREG_SPLIT_NO_EMPTY) as $q) {
                    foreach ($level as $kk => $vv) {
                        $test = $v[$kk] ?? $v->{$kk} ?? "";
                        if (\is_string($test) && "" !== $test && false !== \stripos($test, $q)) {
                            return true;
                        }
                    }
                }
                return false;
            });
            \State::set('count', $count = $pages->count);
            $pager = \Pager::from($pages);
            $pager->hash = $hash;
            $pager->path = '/' . ("" !== $path ? $path : $route);
            $pager->query = $query;
            \lot('pager', $pager = $pager->chunk($chunk, $part));
            \lot('pages', $pages = $pages->chunk($chunk, $part));
            if (0 === $count) {
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
            \lot('t')[] = \i('Search');
            \State::set([
                'has' => [
                    'next' => !!$pager->next,
                    'page' => true,
                    'pages' => true,
                    'parent' => !!$page->parent,
                    'part' => !!($part + 1),
                    'prev' => !!$pager->prev
                ],
                'is' => [
                    'error' => false,
                    'page' => false,
                    'pages' => true
                ]
            ]);
            // Apply the hook only if there is a match
            \Hook::set('page.content', __NAMESPACE__ . "\\page__content", 2.1);
            \Hook::set('page.description', __NAMESPACE__ . "\\page__description", 2.1);
            \Hook::set('page.title', __NAMESPACE__ . "\\page__title", 2.1);
            return ['pages/search', [], 200];
        }
    }
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

\Hook::set('route.page', __NAMESPACE__ . "\\route__page", 100.1);
\Hook::set('route.search', __NAMESPACE__ . "\\route__search", 100);