<?php

$has_key = isset($key) && is_string($key);
$has_path = !empty($url->path);
$has_route = isset($route) && is_string($route);

$is_page = $site->is('page');

$current = $has_path ? $url->current(false, false) : null;
$key = $has_key ? $key : ($state->x->search->key ?? null);
$title = $is_page ? ($page->parent ? ($page->parent->title ?? null) : null) : ($page->title ?? null);

echo new HTML(Hook::fire('y.form.search', [[
    0 => 'form',
    1 => [
        'search' => [
            0 => 'p',
            1 => [
                'input' => [
                    0 => 'input',
                    1 => false,
                    2 => [
                        'name' => null !== $key ? (false !== strpos(strtr($key, ["\\." => P]), '.') ? (static function ($key) {
                            // Convert `foo.bar.baz` to `foo[bar][baz]`
                            $keys = explode('.', strtr($key, ["\\." => P]));
                            $v = strtr(array_shift($keys), [P => '.']);
                            while (is_string($key = array_shift($keys))) {
                                $v .= '[' . strtr($key, [P => '.']) . ']';
                            }
                            return $v;
                        })($key) : strtr($key, ["\\." => '.'])) : null,
                        'placeholder' => $title && !$has_route ? i('Search in %s', ['&#x201c;' . S . $title . S . '&#x201d;']) : null,
                        'type' => 'text',
                        'value' => null !== $key ? get($_GET, $key) : null
                    ]
                ],
                ' ' => ' ',
                'button' => [
                    0 => 'button',
                    1 => i('Search'),
                    2 => ['type' => 'submit']
                ]
            ]
        ]
    ],
    2 => [
        'action' => $has_route ? ($url . '/' . trim($route, '/') . '/1') : ($has_path && $is_page ? dirname($current) . '/1' : null),
        'method' => 'get',
        'name' => 'search'
    ]
]], $page), true);