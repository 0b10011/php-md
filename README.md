[![Build Status](https://travis-ci.org/bfrohs/php-md.png?branch=master)](https://travis-ci.org/bfrohs/php-md)

**Important:** This is an *experiment* and may be changed, rebased, or trashed at any
point. Use extreme caution before using this code.

A few things to track:

- [Core Syntax milestone](https://github.com/bfrohs/php-md/issues?milestone=1&state=open)

- [GitHub Flavored Markdown milestone](https://github.com/bfrohs/php-md/issues?milestone=2&state=open)

---

Developer-friendly markdown parser for PHP.

# Using

```php
// Include class
require_once('/path/to/markdown/source/app.php');

// Make `Markdown` an alias for `bfrohs\markdown\Markdown`
use bfrohs\markdown\Markdown as Markdown;

// Create a new Markdown object with the scope of 'p'
$text = "Some string using *markdown*.";
$markdown = new Markdown($text);

// Convert provided markdown to HTML
$html = $markdown->toHTML();

echo $html; // <p>Some string using <em>markdown</em>.</p>
```

# Differences from [Dingus](http://daringfireball.net/projects/markdown/dingus)

- [Inter-element whitespace][] is discarded

- Whitespace is collapsed into a single space

- Empty elements are output without trailing slash (ie, `<br>` instead of `<br />`)

- Currently no support for:
    - html `<div>`
    - entities `&quot;`
    - autolinks `<http://example.com/>`
    - code blocks `    <?php`
    - reference links `[foo][]`

[Inter-element whitespace]: http://www.whatwg.org/specs/web-apps/current-work/multipage/elements.html#inter-element-whitespace
