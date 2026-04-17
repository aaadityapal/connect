<?php
/**
 * HR summary helper (extractive, no external API).
 * Strategy:
 * - Prefer short description as primary sentence when available.
 * - Add one key sentence from long description containing important keywords.
 * - Keep output concise by capping to max words.
 */

if (!function_exists('hr_clean_summary_text')) {
    function hr_clean_summary_text(?string $text): string
    {
        $text = (string)($text ?? '');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string)$text);
    }
}

if (!function_exists('hr_split_sentences')) {
    function hr_split_sentences(string $text): array
    {
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
        $parts = array_map('trim', $parts);
        return array_values(array_filter($parts, static function ($s) {
            return $s !== '';
        }));
    }
}

if (!function_exists('hr_strtolower_safe')) {
    function hr_strtolower_safe(string $text): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($text, 'UTF-8');
        }
        return strtolower($text);
    }
}

if (!function_exists('hr_stripos_safe')) {
    function hr_stripos_safe(string $haystack, string $needle)
    {
        if (function_exists('mb_stripos')) {
            return mb_stripos($haystack, $needle, 0, 'UTF-8');
        }
        return stripos($haystack, $needle);
    }
}

if (!function_exists('hr_truncate_words')) {
    function hr_truncate_words(string $text, int $maxWords = 40): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $words = preg_split('/\s+/u', $text) ?: [];
        if (count($words) <= $maxWords) {
            return $text;
        }

        $slice = array_slice($words, 0, $maxWords);
        $result = rtrim(implode(' ', $slice), " \t\n\r\0\x0B.,;:!");
        return $result . '...';
    }
}

if (!function_exists('hr_pick_key_sentence')) {
    function hr_pick_key_sentence(array $sentences, string $exclude = ''): string
    {
        $keywords = [
            'mandatory', 'required', 'must', 'deadline', 'effective', 'policy', 'notice',
            'compliance', 'security', 'attendance', 'leave', 'payroll', 'action',
            'submit', 'acknowledge', 'update'
        ];

        $excludeLc = hr_strtolower_safe(trim($exclude));
        foreach ($sentences as $sentence) {
            $candidate = trim((string)$sentence);
            if ($candidate === '') {
                continue;
            }

            $candidateLc = hr_strtolower_safe($candidate);
            if ($excludeLc !== '' && $candidateLc === $excludeLc) {
                continue;
            }

            foreach ($keywords as $kw) {
                if (hr_stripos_safe($candidateLc, $kw) !== false) {
                    return $candidate;
                }
            }
        }

        return '';
    }
}

if (!function_exists('hr_generate_extractive_summary')) {
    function hr_generate_extractive_summary(?string $shortDesc, ?string $longDesc, int $maxWords = 40): string
    {
        $short = hr_clean_summary_text($shortDesc);
        $long = hr_clean_summary_text($longDesc);

        if ($short === '' && $long === '') {
            return '';
        }

        $sentences = hr_split_sentences($long);
        $firstSentence = $sentences[0] ?? '';

        // Primary: prefer short description; fallback to long description first sentence.
        $primary = $short !== '' ? $short : $firstSentence;
        if ($primary === '' && $long !== '') {
            $primary = $long;
        }

        // Key sentence from long description (keyword-driven), excluding primary.
        $keySentence = hr_pick_key_sentence($sentences, $primary);

        $combined = $primary;
        if ($keySentence !== '') {
            $combined .= (preg_match('/[.!?]$/u', $combined) ? ' ' : '. ') . $keySentence;
        }

        $combined = hr_truncate_words($combined, max(20, min(40, $maxWords)));
        return trim($combined);
    }
}
