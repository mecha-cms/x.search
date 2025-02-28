<?php

return [
    'chunk' => 5,
    'deep' => true,
    'key' => 'query',
    'route' => '/search',
    'score' => [
        'content' => 0, // Change this to a value greater than `0` to include the page content as a search target!
        'description' => 3,
        'name' => 1,
        'title' => 2
    ],
    'sort' => [-1, 'search-score']
];