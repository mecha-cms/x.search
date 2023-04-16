<?php namespace x\search;

function mark($content) {
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

function route($content, $path, $query, $hash) {
    $key = \State::get('x.search.key') ?? 0;
    if (0 !== $key && null !== ($search = \get($_GET, $key))) {
        \extract($GLOBALS, \EXTR_SKIP);
        \State::set('has.query', true);
        if (isset($page) && $page->exist && isset($pages) && $pages->count) {
            $chunk = $state->chunk ?? $page->chunk ?? 5;
            $part = ($state->part ?? 1) - 1;
            $pages = $pages->parent->is(function ($v) use ($search) {
                $content = $v['content'] ?? $v->content ?? "";
                $description = $v['description'] ?? $v->description ?? "";
                $name = $v['name'] ?? $v->name ?? "";
                $title = $v['title'] ?? $v->title ?? "";
                foreach (\preg_split('/\s+/', $search, -1, \PREG_SPLIT_NO_EMPTY) as $q) {
                    // Priority: `name`, `title`, `description`, `content`
                    if ($name && false !== \stripos($name, $q)) {
                        return true;
                    }
                    if ($title && false !== \stripos($title, $q)) {
                        return true;
                    }
                    if ($description && false !== \stripos($description, $q)) {
                        return true;
                    }
                    if ($content && false !== \stripos($content, $q)) {
                        return true;
                    }
                }
                return false;
            });
            \State::set('count', $count = $pages->count);
            $pager = \Pager::from($pages);
            $pager->hash = $hash;
            $pager->path = \preg_replace('/\/' . \x($part + 1) . '$/', "", $path);
            $pager->query = $query;
            $GLOBALS['pager'] = $pager = $pager->chunk($chunk, $part);
            $GLOBALS['pages'] = $pages = $pages->chunk($chunk, $part);
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
                $GLOBALS['t'][] = i('Error');
                return ['pages', [], 404];
            }
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
            \Hook::set([
                'page.content',
                'page.description',
                'page.title'
            ], __NAMESPACE__ . "\\mark", 2.1);
            return ['pages', [], 200];
        }
    }
}

\Hook::set('route.page', function ($content, $path, $query, $hash) {
    return \Hook::fire('route.search', [$content, $path, $query, $hash]);
}, 100.1);

\Hook::set('route.search', __NAMESPACE__ . "\\route", 100);

if (\class_exists("\\Layout")) {
    !\Layout::path('form/search') && \Layout::set('form/search', __DIR__ . \D . 'engine' . \D . 'y' . \D . 'form' . \D . 'search.php');
}