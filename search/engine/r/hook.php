<?php namespace _\lot\x\search;

// Highlight the matching part(s)
function mark($content) {
    if ($query = \preg_split('/\s+/', (string) \Get::get(\State::get('x.search.key')))) {
        $parts = \preg_split('/(<[^>]+>)/', $content, null, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);
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

\Hook::set([
    'page.content',
    'page.description',
    'page.excerpt', // `.\lot\x\excerpt`
    'page.title'
], __NAMESPACE__ . "\\mark", 2.1);
