[![Build Status](https://travis-ci.org/bfrohs/php-md.png)](https://travis-ci.org/bfrohs/php-md)

**Important:** This is an *experiment* and may be changed, rebased, or trashed at any
point. Use extreme caution before using this code.

---

When presented with a choice between simpler parsing and parser correctness (according to
specification/"dingus"), simpler parsing will almost always be chosen.

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