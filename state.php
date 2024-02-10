<?php

return [
    'chunk' => 5,
    'deep' => true,
    'key' => 'query',
    'level' => [
        'name' => 1,
        'title' => 2,
        'description' => 3,
        'content' => 0 // Change the value to be greater than `0` to include page content as the search target as well
    ],
    'route' => '/search',
    'sort' => [1, 'path']
];