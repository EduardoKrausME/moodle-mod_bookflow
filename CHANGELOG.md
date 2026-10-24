# Changelog

This changelog documents only the two most recent published GitHub releases. Each entry contains changes verified against its immediately preceding published release. Changes made later on `master` are excluded.

## 1.3.4 - 2026-10-23

### Fixed

- Introduced `mod_bookflow\event\course_module_viewed`, extending Moodle's standard activity-view event, and updated `view.php` to trigger it for more consistent activity logging and reporting.
- Protected learner-specific progress, last-read position, notes, and bookmarks from being read or modified through shared guest sessions. Guest users no longer persist page-view progress or see editing/answer-submission controls for questions; bookmark and note pages and the question-submission endpoint explicitly reject guest actions.
- Restricted both the page-based and external-API content searches to the content title and `data1` text, excluding `data2` and `data3` fields that may contain option metadata or private answer keys. The existing capability-based hidden-content filter remains in place.
- Excluded hidden chapters from Markdown export, in addition to the existing hidden-content filtering. This also protects exports that reuse the Markdown representation.
- Added `tests/security_regression_test.php` covering standard activity-view events, search confidentiality, guest user state isolation, and hidden-content exports; subsequent commits refined PHPUnit isolation, guest session setup, and CodeSniffer-compatible test annotations.

### Changed

- Replaced `core_text::strlen` with `strlen` in the page-based and external-API search length checks.
- Updated the plugin release identifier from `1.3.3` to `1.3.4` and its build number from `2026101900` to `2026102300`.

[Compare 1.3.3...1.3.4](https://github.com/EduardoKrausME/moodle-mod_bookflow/compare/1.3.3...1.3.4)

## 1.3.3 - 2026-10-19

### Changed

- Added the plugin icon at `pix/icon.png` and reorganized the SVG logo asset as `pix/monologo.svg`.
- Removed the older `.github/workflows/auto-release.yml` workflow from the release-tag comparison.
- Added concurrency settings to the remaining `.github/workflows/release.yml` workflow, using a group based on `github.ref`, `cancel-in-progress: false`, and `queue: max`.
- Updated the plugin release identifier from `1.3.2` to `1.3.3` and its build number from `2026101500` to `2026101900`.

No changes to BookFlow activity functionality were identified in this tag comparison.

[Compare 1.3.2...1.3.3](https://github.com/EduardoKrausME/moodle-mod_bookflow/compare/1.3.2...1.3.3)
