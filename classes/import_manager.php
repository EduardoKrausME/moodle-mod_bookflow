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
 * import_manager.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook;

use DOMDocument;
use DOMXPath;
use invalid_parameter_exception;
use moodle_exception;
use ZipArchive;

/**
 * Imports supported source formats into a FlexBook.
 */
class import_manager {
    /**
     * Imports content from standard book.
     *
     * @param int $bookid Standard Book activity ID.
     * @param int $flexbookid FlexBook ID.
     * @return int
     */
    public static function from_standard_book(int $bookid, int $flexbookid): int {
        global $DB;

        $book = $DB->get_record("book", ["id" => $bookid], "*", MUST_EXIST);
        if ($book->course != $DB->get_field("flexbook", "course", ["id" => $flexbookid], MUST_EXIST)) {
            throw new invalid_parameter_exception("The Book belongs to another course");
        }
        $count = 0;
        $parentmap = [];
        foreach ($DB->get_records("book_chapters", ["bookid" => $bookid], "pagenum, id") as $bookchapter) {
            $parentid = 0;
            if ($bookchapter->subchapter && $parentmap) {
                $parentid = end($parentmap);
            }
            $chapterid = chapter_manager::create((object) [
                "flexbookid" => $flexbookid,
                "parentid" => $parentid,
                "title" => $bookchapter->title,
                "description" => "",
                "descriptionformat" => FORMAT_HTML,
                "hidden" => $bookchapter->hidden,
                "required" => 0,
                "estimatedtime" => 0,
            ]);
            if (!$bookchapter->subchapter) {
                $parentmap[] = $chapterid;
            }
            content_manager::create((object) [
                "chapterid" => $chapterid,
                "type" => "html",
                "title" => $bookchapter->title,
                "data1" => $bookchapter->content,
                "data2" => "",
                "data3" => "",
                "auxint1" => 0,
                "auxint2" => 0,
                "auxint3" => 0,
                "trackprogress" => 1,
                "required" => 0,
                "weight" => 1,
                "completiontype" => "view",
                "completionvalue" => 0,
                "hidden" => $bookchapter->hidden,
                "estimatedtime" => 0,
            ]);
            $count++;
        }
        return $count;
    }

    /**
     * Imports content from Markdown.
     *
     * @param string $markdown Markdown source.
     * @param int $flexbookid FlexBook ID.
     * @param string $fallbacktitle Fallback chapter title.
     * @return int
     */
    public static function from_markdown(string $markdown, int $flexbookid, string $fallbacktitle = ""): int {
        $parts = preg_split("/^(#{1,2})\s+(.+)$/m", $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!$parts || count($parts) < 4) {
            self::create_text_chapter($flexbookid, $fallbacktitle ?: get_string("importedcontent", "mod_flexbook"),
                $markdown, "markdown", 0);
            return 1;
        }

        $count = 0;
        $parentid = 0;
        for ($index = 1; $index < count($parts); $index += 3) {
            $level = strlen($parts[$index]);
            $title = trim($parts[$index + 1]);
            $body = trim($parts[$index + 2] ?? "");
            $chapterparent = $level == 2 ? $parentid : 0;
            $chapterid = self::create_text_chapter(
                $flexbookid,
                $title,
                $body,
                "markdown",
                $chapterparent
            );
            if ($level == 1) {
                $parentid = $chapterid;
            }
            $count++;
        }
        return $count;
    }

    /**
     * Imports content from HTML.
     *
     * @param string $html HTML source.
     * @param int $flexbookid FlexBook ID.
     * @param string $fallbacktitle Fallback chapter title.
     * @return int
     */
    public static function from_html(string $html, int $flexbookid, string $fallbacktitle = ""): int {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            "<?xml encoding=\"utf-8\" ?><body>{$html}</body>",
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new moodle_exception("invalidhtml", "mod_flexbook");
        }

        $xpath = new DOMXPath($document);
        $headings = $xpath->query("//h1|//h2");
        if (!$headings || !$headings->length) {
            self::create_text_chapter($flexbookid, $fallbacktitle ?: get_string("importedcontent", "mod_flexbook"),
                $html, "html", 0);
            return 1;
        }

        $count = 0;
        $parentid = 0;
        foreach ($headings as $heading) {
            $body = "";
            $node = $heading->nextSibling;
            while ($node && !in_array(strtolower($node->nodeName), ["h1", "h2"])) {
                $body .= $document->saveHTML($node);
                $node = $node->nextSibling;
            }
            $level = strtolower($heading->nodeName) == "h2" ? 2 : 1;
            $chapterid = self::create_text_chapter(
                $flexbookid,
                trim($heading->textContent),
                $body,
                "html",
                $level == 2 ? $parentid : 0
            );
            if ($level == 1) {
                $parentid = $chapterid;
            }
            $count++;
        }
        return $count;
    }

    /**
     * Imports content from Markdown ZIP.
     *
     * @param string $path Source file path.
     * @param int $flexbookid FlexBook ID.
     * @return int
     */
    public static function from_markdown_zip(string $path, int $flexbookid): int {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new moodle_exception("invalidzip", "mod_flexbook");
        }
        $count = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (!preg_match("/\.(md|markdown)$/i", $name) || str_contains($name, "..")) {
                continue;
            }
            $markdown = $zip->getFromIndex($index);
            if ($markdown === false) {
                continue;
            }
            $title = pathinfo(basename($name), PATHINFO_FILENAME);
            $count += self::from_markdown($markdown, $flexbookid, $title);
        }
        $zip->close();
        return $count;
    }

    /**
     * Creates a chapter containing an imported text block.
     *
     * @param int $flexbookid FlexBook ID.
     * @param string $title Imported chapter title.
     * @param string $body Imported chapter content.
     * @param string $type Content type identifier.
     * @param int $parentid Parent chapter ID.
     * @return int
     */
    private static function create_text_chapter(
        int $flexbookid,
        string $title,
        string $body,
        string $type,
        int $parentid
    ): int {
        $chapterid = chapter_manager::create((object) [
            "flexbookid" => $flexbookid,
            "parentid" => $parentid,
            "title" => clean_param($title, PARAM_TEXT),
            "description" => "",
            "descriptionformat" => FORMAT_HTML,
            "hidden" => 0,
            "required" => 0,
            "estimatedtime" => 0,
        ]);
        content_manager::create((object) [
            "chapterid" => $chapterid,
            "type" => $type,
            "title" => clean_param($title, PARAM_TEXT),
            "data1" => $body,
            "data2" => "",
            "data3" => "",
            "auxint1" => 0,
            "auxint2" => 0,
            "auxint3" => 0,
            "trackprogress" => 1,
            "required" => 0,
            "weight" => 1,
            "completiontype" => "view",
            "completionvalue" => 0,
            "hidden" => 0,
            "estimatedtime" => 0,
        ]);
        return $chapterid;
    }
}
