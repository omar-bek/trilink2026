<?php

namespace App\Support;

/**
 * Pre-shapes Arabic text into Unicode "Arabic Presentation Forms-B"
 * (U+FE70 – U+FEFF) so renderers without GSUB shaping support — notably
 * dompdf with its bundled DejaVu Sans — display Arabic letters in their
 * correct contextual form (initial / medial / final) instead of the
 * disconnected isolated forms that produce text like "ةداملا" instead of
 * "المادة".
 *
 * It also reverses Arabic runs visually so that a downstream renderer
 * which prints text left-to-right (also a dompdf limitation: BiDi
 * reordering is incomplete) shows the run in the correct visual order.
 *
 * Latin text, digits, punctuation and whitespace pass through unchanged.
 * Mixed strings ("AED 100.00 المتبقي") are handled by walking BiDi runs:
 * Arabic chunks are shaped + reversed, non-Arabic chunks are left intact.
 *
 * Algorithm reference: Unicode Standard Annex #9 (Bidirectional Algorithm)
 * + Unicode Standard "Arabic shaping classes" (Joining_Type property).
 * Limited but sufficient for the contract template clauses.
 */
class ArabicShaper
{
    /**
     * Map base codepoint => [isolated, final, initial, medial].
     * `null` means the form does not exist for that letter (right-joining
     * letters have no initial / medial form).
     */
    private const FORMS = [
        0x0621 => [0xFE80, null,   null,   null  ], // ء  Hamza
        0x0622 => [0xFE81, 0xFE82, null,   null  ], // آ  Alef Madda
        0x0623 => [0xFE83, 0xFE84, null,   null  ], // أ  Alef Hamza Above
        0x0624 => [0xFE85, 0xFE86, null,   null  ], // ؤ  Waw Hamza Above
        0x0625 => [0xFE87, 0xFE88, null,   null  ], // إ  Alef Hamza Below
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C], // ئ  Yeh Hamza Above
        0x0627 => [0xFE8D, 0xFE8E, null,   null  ], // ا  Alef
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92], // ب  Beh
        0x0629 => [0xFE93, 0xFE94, null,   null  ], // ة  Teh Marbuta
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98], // ت  Teh
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C], // ث  Theh
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0], // ج  Jeem
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4], // ح  Hah
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8], // خ  Khah
        0x062F => [0xFEA9, 0xFEAA, null,   null  ], // د  Dal
        0x0630 => [0xFEAB, 0xFEAC, null,   null  ], // ذ  Thal
        0x0631 => [0xFEAD, 0xFEAE, null,   null  ], // ر  Reh
        0x0632 => [0xFEAF, 0xFEB0, null,   null  ], // ز  Zain
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4], // س  Seen
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8], // ش  Sheen
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC], // ص  Sad
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0], // ض  Dad
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4], // ط  Tah
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8], // ظ  Zah
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC], // ع  Ain
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0], // غ  Ghain
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4], // ف  Feh
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8], // ق  Qaf
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC], // ك  Kaf
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0], // ل  Lam
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4], // م  Meem
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8], // ن  Noon
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC], // ه  Heh
        0x0648 => [0xFEED, 0xFEEE, null,   null  ], // و  Waw
        0x0649 => [0xFEEF, 0xFEF0, null,   null  ], // ى  Alef Maksura
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4], // ي  Yeh
    ];

    /**
     * Letters that join only to the previous letter (they have a final form
     * but no initial / medial form). Used to decide whether the next letter
     * can use a final or medial form — these letters do not "extend" the
     * cursive run forward, so the letter following them must use isolated
     * or initial form.
     */
    private const RIGHT_JOINING_ONLY = [
        0x0621, 0x0622, 0x0623, 0x0624, 0x0625, 0x0627, 0x0629,
        0x062F, 0x0630, 0x0631, 0x0632, 0x0648, 0x0649,
    ];

    /**
     * Lam-Alef ligatures: when a Lam (U+0644) is immediately followed by
     * an Alef variant the pair is fused into a single ligature glyph.
     * Each entry is [alef-codepoint, isolated-ligature, final-ligature].
     * The ligature itself behaves like a right-joining letter (so the
     * letter BEFORE the lam decides whether the ligature uses isolated
     * or final form).
     */
    private const LAM_ALEF = [
        0x0622 => [0xFEF5, 0xFEF6], // لآ
        0x0623 => [0xFEF7, 0xFEF8], // لأ
        0x0625 => [0xFEF9, 0xFEFA], // لإ
        0x0627 => [0xFEFB, 0xFEFC], // لا
    ];

    /** Combining marks (Arabic diacritics) — skipped during shaping context lookup. */
    private const COMBINING_RANGES = [
        [0x064B, 0x065F], // Tanwin + harakat
        [0x0670, 0x0670], // Superscript Alef
        [0x06D6, 0x06ED], // Quranic marks
    ];

    /**
     * Unicode bidi bracket-mirroring pairs. When a bracket character
     * appears inside an RTL paragraph, it must be visually mirrored: an
     * opening "(" appearing logically before an Arabic phrase needs to be
     * displayed as ")" because in RTL the opening edge of a parenthesised
     * group is on the right side. dompdf does not do this mirroring on
     * its own, so the shaper swaps the codepoint when it knows the line
     * is being rendered in RTL context (i.e. there is at least one
     * Arabic letter present).
     */
    private const BIDI_MIRRORS = [
        0x0028 => 0x0029, // ( → )
        0x0029 => 0x0028, // ) → (
        0x005B => 0x005D, // [ → ]
        0x005D => 0x005B, // ] → [
        0x007B => 0x007D, // { → }
        0x007D => 0x007B, // } → {
        0x003C => 0x003E, // < → >
        0x003E => 0x003C, // > → <
        0x00AB => 0x00BB, // « → »
        0x00BB => 0x00AB, // » → «
        0x2039 => 0x203A, // ‹ → ›
        0x203A => 0x2039, // › → ‹
    ];

    /**
     * Pre-shape Arabic text in `$text` into presentation forms and reverse
     * each Arabic run so the visual order is correct when the host renderer
     * prints text left-to-right.
     *
     * Idempotent for non-Arabic input — Latin text, numbers, punctuation
     * and existing presentation-form codepoints are passed through unchanged
     * (except presentation forms are reversed alongside their Arabic run).
     */
    public static function shape(?string $text): string
    {
        if ($text === null || $text === '') {
            return (string) $text;
        }

        // Passes 1 + 2: fold lam-alef ligatures and apply contextual shaping.
        // Both `shape()` and `shapeBlock()` reuse this so the per-letter
        // analysis logic only lives in one place.
        $shaped = self::shapeFlat($text);

        // Pass 3: BiDi-style visual reordering for an LTR renderer.
        //
        // We split the shaped codepoint stream into runs:
        //   - Arabic runs   : maximal sequences of Arabic letters
        //   - Latin runs    : maximal sequences of Latin / digit / punct
        //   - Neutral runs  : single whitespace (or other neutral) char
        //
        // Then we (a) reverse the codepoints within each Arabic run so the
        // word reads correctly RTL when printed LTR, and (b) reverse the
        // ORDER of the runs themselves so the FIRST word in logical order
        // ends up RIGHTMOST on the page (which is where an Arabic reader
        // expects it). Latin runs are left in their natural LTR order so
        // English words / numbers stay readable.
        //
        // If the input contains no Arabic at all we skip both reorderings
        // entirely — pure Latin / numeric strings must pass through
        // unchanged.
        $hasArabic = false;
        foreach ($shaped as $cp) {
            if (self::isArabicVisual($cp)) {
                $hasArabic = true;
                break;
            }
        }

        if (!$hasArabic) {
            return self::codepointsToUtf8($shaped);
        }

        // Build the run list. Each run is ['type' => 'A'|'L'|'N', 'chars' => array].
        $runs = [];
        $count = count($shaped);
        $i = 0;
        while ($i < $count) {
            $cp = $shaped[$i];
            if (self::isArabicVisual($cp)) {
                $start = $i;
                while ($i < $count && self::isArabicVisual($shaped[$i])) {
                    $i++;
                }
                $runs[] = ['type' => 'A', 'chars' => array_slice($shaped, $start, $i - $start)];
            } elseif (self::isNeutral($cp)) {
                // Neutrals (spaces) get their own run so the BiDi-order
                // reversal puts them between the surrounding runs at the
                // correct visual position.
                $runs[] = ['type' => 'N', 'chars' => [$cp]];
                $i++;
            } else {
                $start = $i;
                while ($i < $count && !self::isArabicVisual($shaped[$i]) && !self::isNeutral($shaped[$i])) {
                    $i++;
                }
                $runs[] = ['type' => 'L', 'chars' => array_slice($shaped, $start, $i - $start)];
            }
        }

        // Reverse Arabic run contents (each Arabic word goes RTL when
        // printed LTR by dompdf). For Latin and Neutral runs, swap any
        // bracket characters to their bidi mirror — an opening "(" that
        // logically precedes an Arabic phrase needs to be displayed as
        // ")" because the visual opening edge is on the right side in
        // RTL paragraphs.
        foreach ($runs as &$run) {
            if ($run['type'] === 'A') {
                $run['chars'] = array_reverse($run['chars']);
            } else {
                foreach ($run['chars'] as $idx => $cp) {
                    if (isset(self::BIDI_MIRRORS[$cp])) {
                        $run['chars'][$idx] = self::BIDI_MIRRORS[$cp];
                    }
                }
            }
        }
        unset($run);

        // Reverse the order of all runs (so the first logical word ends
        // up rightmost on the page).
        $runs = array_reverse($runs);

        // Concatenate.
        $output = '';
        foreach ($runs as $run) {
            $output .= self::codepointsToUtf8($run['chars']);
        }

        return $output;
    }

    /**
     * Break a long string into multiple lines at word boundaries so each
     * line, when rendered through `shape()` and printed by dompdf in LTR
     * order, reads correctly right-to-left without dompdf ever needing
     * to wrap the line itself.
     *
     * This sidesteps the fundamental limitation we hit with `shape()` on
     * paragraph-length text: when dompdf wraps a fully-reversed string,
     * the LAST words of the original sentence end up on the FIRST visual
     * line and the FIRST words on the LAST line, producing a vertically
     * inverted paragraph. We can't rely on dompdf for BiDi-aware inline
     * layout (verified empirically: dompdf does not honour `direction:
     * rtl` for inline element ordering), so the caller must do its own
     * wrapping. Each returned line is short enough that it fits on a
     * single visual row in the target column, eliminating dompdf's wrap
     * step entirely.
     *
     * The split is character-count based (`$maxChars`), measured at word
     * boundaries. It's an approximation — Arabic letters are
     * proportional, not monospaced — but for the contract clause column
     * width and Arial 10.5px, ~75 chars per line lands within the column
     * boundary in practice.
     *
     * @return array<int, string>
     */
    public static function breakIntoLines(string $text, int $maxChars = 75): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $candidate = $current === '' ? $word : ($current . ' ' . $word);
            if (mb_strlen($candidate) > $maxChars && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * Internal: run passes 1 + 2 of the shaper (lam-alef folding + per
     * character contextual form selection) and return the shaped codepoint
     * array in LOGICAL order (no BiDi reordering yet).
     *
     * @return array<int, int>
     */
    private static function shapeFlat(string $text): array
    {
        $codes = self::utf8ToCodepoints($text);
        $count = count($codes);

        // Pass 1: fold Lam-Alef pairs into single ligature codepoints. We
        // do this BEFORE shaping context lookup so the ligature is treated
        // as one indivisible (right-joining) letter.
        $folded = [];
        for ($i = 0; $i < $count; $i++) {
            $cp = $codes[$i];
            if ($cp === 0x0644 && $i + 1 < $count && isset(self::LAM_ALEF[$codes[$i + 1]])) {
                $folded[] = ['lam_alef', $codes[$i + 1]];
                $i++;
                continue;
            }
            $folded[] = $cp;
        }

        // Pass 2: per-character context analysis + form selection.
        $shaped = [];
        $foldedCount = count($folded);
        for ($i = 0; $i < $foldedCount; $i++) {
            $entry = $folded[$i];

            if (is_array($entry) && $entry[0] === 'lam_alef') {
                $alef = $entry[1];
                $prevJoinsThis = self::previousJoinsForward($folded, $i);
                $shaped[] = self::LAM_ALEF[$alef][$prevJoinsThis ? 1 : 0];
                continue;
            }

            $cp = $entry;
            if (!isset(self::FORMS[$cp])) {
                $shaped[] = $cp;
                continue;
            }

            $prevJoinsThis = self::previousJoinsForward($folded, $i);
            $nextAccepts   = self::nextAcceptsBackward($folded, $i);
            $thisExtends   = !in_array($cp, self::RIGHT_JOINING_ONLY, true);

            if ($prevJoinsThis && $nextAccepts && $thisExtends) {
                $form = self::FORMS[$cp][3] ?? self::FORMS[$cp][1] ?? self::FORMS[$cp][0];
            } elseif ($prevJoinsThis) {
                $form = self::FORMS[$cp][1] ?? self::FORMS[$cp][0];
            } elseif ($nextAccepts && $thisExtends) {
                $form = self::FORMS[$cp][2] ?? self::FORMS[$cp][0];
            } else {
                $form = self::FORMS[$cp][0];
            }

            $shaped[] = $form;
        }

        return $shaped;
    }

    /** Whitespace / direction-neutral characters that get their own run. */
    private static function isNeutral(int $cp): bool
    {
        return $cp === 0x20    // space
            || $cp === 0x09    // tab
            || $cp === 0x0A    // LF
            || $cp === 0x0D    // CR
            || $cp === 0xA0;   // non-breaking space
    }

    /** Determine whether the previous non-mark letter joins to this position. */
    private static function previousJoinsForward(array $folded, int $i): bool
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            $entry = $folded[$j];
            if (is_array($entry) && $entry[0] === 'lam_alef') {
                // Lam-Alef is right-joining — does NOT join forward.
                return false;
            }
            $cp = $entry;
            if (self::isCombiningMark($cp)) {
                continue;
            }
            if (!isset(self::FORMS[$cp])) {
                return false;
            }
            // Hamza (0x0621) is non-joining; right-joining-only letters do
            // not extend forward; everyone else does.
            if ($cp === 0x0621) {
                return false;
            }
            return !in_array($cp, self::RIGHT_JOINING_ONLY, true);
        }
        return false;
    }

    /** Determine whether the next non-mark letter accepts a join from this position. */
    private static function nextAcceptsBackward(array $folded, int $i): bool
    {
        $count = count($folded);
        for ($j = $i + 1; $j < $count; $j++) {
            $entry = $folded[$j];
            if (is_array($entry) && $entry[0] === 'lam_alef') {
                // Ligature accepts a join from the previous letter (it
                // starts with a Lam, which is dual-joining).
                return true;
            }
            $cp = $entry;
            if (self::isCombiningMark($cp)) {
                continue;
            }
            if (!isset(self::FORMS[$cp])) {
                return false;
            }
            // Hamza is non-joining and does not accept a join.
            return $cp !== 0x0621;
        }
        return false;
    }

    private static function isCombiningMark(int $cp): bool
    {
        foreach (self::COMBINING_RANGES as [$lo, $hi]) {
            if ($cp >= $lo && $cp <= $hi) {
                return true;
            }
        }
        return false;
    }

    /**
     * "Visually Arabic" — the codepoints we treat as belonging to an Arabic
     * run for the purpose of run-reversal. Includes the base Arabic block,
     * the presentation forms, the Arabic punctuation we want to flow with
     * the run, and Arabic diacritics.
     */
    private static function isArabicVisual(int $cp): bool
    {
        // Arabic + Arabic Supplement
        if ($cp >= 0x0600 && $cp <= 0x06FF) return true;
        if ($cp >= 0x0750 && $cp <= 0x077F) return true;
        // Arabic Presentation Forms-A
        if ($cp >= 0xFB50 && $cp <= 0xFDFF) return true;
        // Arabic Presentation Forms-B
        if ($cp >= 0xFE70 && $cp <= 0xFEFF) return true;
        return false;
    }

    /** Decode a UTF-8 string to an array of codepoints. */
    private static function utf8ToCodepoints(string $text): array
    {
        $out  = [];
        $len  = strlen($text);
        for ($i = 0; $i < $len;) {
            $c = ord($text[$i]);
            if ($c < 0x80) {
                $out[] = $c;
                $i++;
            } elseif (($c & 0xE0) === 0xC0) {
                $out[] = (($c & 0x1F) << 6) | (ord($text[$i + 1]) & 0x3F);
                $i += 2;
            } elseif (($c & 0xF0) === 0xE0) {
                $out[] = (($c & 0x0F) << 12)
                       | ((ord($text[$i + 1]) & 0x3F) << 6)
                       |  (ord($text[$i + 2]) & 0x3F);
                $i += 3;
            } else {
                $out[] = (($c & 0x07) << 18)
                       | ((ord($text[$i + 1]) & 0x3F) << 12)
                       | ((ord($text[$i + 2]) & 0x3F) << 6)
                       |  (ord($text[$i + 3]) & 0x3F);
                $i += 4;
            }
        }
        return $out;
    }

    /** Encode an array of codepoints back into a UTF-8 string. */
    private static function codepointsToUtf8(array $codes): string
    {
        $out = '';
        foreach ($codes as $cp) {
            if ($cp < 0x80) {
                $out .= chr($cp);
            } elseif ($cp < 0x800) {
                $out .= chr(0xC0 | ($cp >> 6))
                     .  chr(0x80 | ($cp & 0x3F));
            } elseif ($cp < 0x10000) {
                $out .= chr(0xE0 | ($cp >> 12))
                     .  chr(0x80 | (($cp >> 6) & 0x3F))
                     .  chr(0x80 | ($cp & 0x3F));
            } else {
                $out .= chr(0xF0 | ($cp >> 18))
                     .  chr(0x80 | (($cp >> 12) & 0x3F))
                     .  chr(0x80 | (($cp >> 6) & 0x3F))
                     .  chr(0x80 | ($cp & 0x3F));
            }
        }
        return $out;
    }
}
