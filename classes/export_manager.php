<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * export_manager.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook;

use context_module;
use renderer_base;

/**
 * Exports a FlexBook to supported output formats.
 */
class export_manager {
    /**
     * Exports the FlexBook to Markdown.
     *
     * @param int $flexbookid FlexBook ID.
     * @param context_module $context Module context.
     * @return string
     */
    public static function to_markdown(int $flexbookid, context_module $context): string {
        global $DB;

        $flexbook = $DB->get_record("flexbook", ["id" => $flexbookid], "*", MUST_EXIST);
        $markdown = "# " . format_string($flexbook->name, true, ["context" => $context]) . "\n\n";
        $markdown .= trim(html_to_text($flexbook->intro ?? "", 0, false)) . "\n\n";
        foreach ($DB->get_records("flexbook_chapters", ["flexbookid" => $flexbookid], "sortorder, id") as $chapter) {
            $markdown .= ($chapter->parentid ? "## " : "# ") . format_string($chapter->title) . "\n\n";
            foreach ($DB->get_records("flexbook_contents", ["chapterid" => $chapter->id], "sortorder, id") as $content) {
                if ($content->hidden) {
                    continue;
                }
                if ($content->title) {
                    $markdown .= "### " . format_string($content->title) . "\n\n";
                }
                $type = content_type_manager::create_content($content, $flexbook, $context);
                $markdown .= $type->export_markdown();
            }
        }
        return $markdown;
    }

    /**
     * Exports the FlexBook to HTML.
     *
     * @param int $flexbookid FlexBook ID.
     * @param context_module $context Module context.
     * @param renderer_base $output Moodle renderer used to render the Mustache template.
     * @param bool $showanswers Whether hidden answers must be included.
     * @return string
     */
    public static function to_html(
        int $flexbookid,
        context_module $context,
        renderer_base $output,
        bool $showanswers = false
    ): string {
        global $DB;

        $flexbook = $DB->get_record("flexbook", ["id" => $flexbookid], "*", MUST_EXIST);
        $chapters = $DB->get_records("flexbook_chapters", ["flexbookid" => $flexbookid], "sortorder, id");
        $toc = "";
        $body = "";
        foreach ($chapters as $chapter) {
            if ($chapter->hidden) {
                continue;
            }
            $title = format_string($chapter->title);
            $toc .= "<li><a href=\"#chapter-{$chapter->id}\">{$title}</a></li>";
            $level = $chapter->parentid ? "h2" : "h1";
            $body .= "<section class=\"chapter\" id=\"chapter-{$chapter->id}\"><{$level}>{$title}</{$level}>";

            $contents = $DB->get_records("flexbook_contents", ["chapterid" => $chapter->id], "sortorder, id");
            foreach ($contents as $content) {
                if ($content->hidden) {
                    continue;
                }
                $type = content_type_manager::create_content($content, $flexbook, $context);
                $body .= "<div class=\"content content-{$content->type}\">"
                    . $type->render($output, false)
                    . "</div>";
                $body .= $type->render_export_extra($showanswers);
            }
            $body .= "</section>";
        }
        $name = format_string($flexbook->name);
        $language = s(current_language());
        return "<!doctype html><html lang=\"{$language}\"><head><meta charset=\"utf-8\">"
            . "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">"
            . "<title>{$name}</title><style>"
            . "body{max-width:900px;margin:2rem auto;font:16px/1.6 system-ui;color:#172033;padding:0 1rem}"
            . "img,video{max-width:100%}.chapter{break-before:page}.content{margin:1.5rem 0}"
            . "@media print{a[href]::after{content:' (' attr(href) ')';font-size:.8em}}"
            . "</style></head><body><header><h1>{$name}</h1>"
            . format_text($flexbook->intro, $flexbook->introformat, ["context" => $context])
            . "</header><nav><h2>" . get_string("tableofcontents", "mod_flexbook") . "</h2><ol>{$toc}</ol></nav>"
            . $body . "</body></html>";
    }

    /**
     * Creates a ZIP archive containing the exported book and its files.
     *
     * @param int $flexbookid FlexBook ID.
     * @param context_module $context Module context.
     * @param renderer_base $output Moodle renderer used to render the Mustache template.
     * @return string
     */
    public static function create_zip(
        int $flexbookid,
        context_module $context,
        renderer_base $output
    ): string {
        $directory = make_request_directory();
        $htmlpath = $directory . "/index.html";
        $markdownpath = $directory . "/flexbook.md";
        file_put_contents($htmlpath, self::to_html($flexbookid, $context, $output));
        file_put_contents($markdownpath, self::to_markdown($flexbookid, $context));
        $zippath = $directory . "/flexbook.zip";
        $packer = get_file_packer("application/zip");
        $packer->archive_to_pathname([
            "index.html" => $htmlpath,
            "flexbook.md" => $markdownpath,
        ], $zippath);
        return $zippath;
    }

    /**
     * Creates a temporary text export file.
     *
     * @param string $content Exported content.
     * @param string $extension Output file extension.
     * @return string
     */
    public static function create_text_file(string $content, string $extension): string {
        $directory = make_request_directory();
        $path = $directory . "/flexbook." . clean_param($extension, PARAM_ALPHANUMEXT);
        file_put_contents($path, $content);
        return $path;
    }
}
