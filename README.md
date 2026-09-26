# FlexBook

`mod_flexbook` is a modern interactive-book activity for Moodle 4.4 or newer.
It keeps content in independent blocks, tracks semantic reading state, calculates
weighted progress and updates Moodle activity completion through
`\completion_info`.

## Included in this release

- chapters and subchapters;
- HTML, Markdown, callout, image, video, audio, download, code, accordion, tabs,
  disclosure, flashcards and question blocks;
- editing actions with AJAX sorting and non-JavaScript move links;
- weighted progress, ignored blocks, required blocks and required chapters;
- percentage, required, combined and required-chapter completion modes;
- resume from the last chapter and block;
- private bookmarks, notes and highlights;
- internal search;
- student, chapter and content reports using `flexible_table`;
- Standard Book, HTML, Markdown and Markdown ZIP import;
- HTML, Markdown, ZIP and print export;
- activity and user-data backup and restore;
- Privacy API, events, PHPUnit tests and Behat scenarios;
- English and Brazilian Portuguese.

No custom renderer or renderable class is used. PHP classes pass data directly
to Mustache through Moodle's standard `$OUTPUT->render_from_template()` method.

## Installation

Copy the `flexbook` directory to:

```text
mod/flexbook
```

Then open Site administration and complete the Moodle upgrade.

## Progress rules

Only visible blocks with `trackprogress = 1` enter the denominator:

```text
completed percentage = completed weight / tracked weight * 100
```

The server clamps the result to the 0 to 100 range, ignores duplicate
completion, checks block ownership and validates each block's configured rule.
Opening a chapter never marks all its blocks as viewed.

## Question block data

For the built-in question block:

- `data1`: question text as HTML;
- `data2`: JSON option array;
- `data3`: JSON configuration with `answer` and optional `feedback`.

Example:

```json
[
  {"title": "Option A", "value": "a"},
  {"title": "Option B", "value": "b"}
]
```

```json
{"answer": "b", "feedback": "Review the explanation above."}
```

## Extending content types

FlexBook declares the `flexbookcontent` subplugin type. A subplugin can register
a class without changing `mod_flexbook`.

The content class must extend:

```php
\mod_flexbook\types\content
```

A conventional registration class can be placed at:

```text
flexbookcontent_example/classes/local/content_type.php
```

with:

```php
public static function register(\mod_flexbook\hook\content_types $hook): void {
    $hook->register("example", \flexbookcontent_example\types\example::class);
}
```

Plugins can also subscribe to `\mod_flexbook\hook\content_types` through
Moodle's hook manager and call the same `register()` method.
