<?php namespace _\lot\x\search;

// Highlight the matching text
function mark($content) {
    $out = "";
    if ($query = \preg_split('/\s+/', \HTTP::get(\state('search')['key']))) {
        foreach (\preg_split('/(<[^>]+>)/', $content, null, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) as $v) {
            if ($v && $v[0] === '<' && \substr($v, -1) === '>') {
                $out .= $v; // Ignore HTML tag(s)
            } else {
                foreach ($query as $q) {
                    if (\stripos($v, $q) === false) {
                        continue;
                    }
                    $v = \preg_replace('/' . \x($q) . '/i', '<mark>$0</mark>', $v);
                }
                $out .= $v;
            }
        }
    }
    return $out;
}

\Hook::set([
    'page.content',
    'page.description',
    'page.excerpt', // `.\lot\x\excerpt`
    'page.title'
], __NAMESPACE__ . "\\mark", 2.1);