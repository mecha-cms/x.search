<?php

$current = $url->current(false, false);
$key = isset($key) && is_string($key) ? $key : ($state->x->search->key ?? null);
$title = $site->is('page') ? ($page->parent ? ($page->parent->title ?? null) : null) : ($page->title ?? null);

echo new HTML(Hook::fire('y.form.search', [[
    0 => 'form',
    1 => [
        'self' => [
            0 => 'p',
            1 => [
                'input' => [
                    0 => 'input',
                    1 => false,
                    2 => [
                        'name' => $key ? (false !== strpos($key, '.') ? (static function ($key) {
                            $keys = explode('.', strtr($key, ["\\." => P]));
                            $v = strtr(array_shift($keys), [P => '.']);
                            while ($key = array_shift($keys)) {
                                $v .= '[' . strtr($key, [P => '.']) . ']';
                            }
                            return $v;
                        })($key) : $key) : null,
                        'placeholder' => $title ? i('Search in %s', ['&#x201c;' . S . $title . S . '&#x201d;']) : null,
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
        'action' => isset($route) && is_string($route) ? ($url . '/' . trim($route, '/')) : ($site->is('page') && $current !== (string) $url ? dirname($current) : $current),
        'method' => 'get',
        'name' => 'search'
    ]
]], $page), true);