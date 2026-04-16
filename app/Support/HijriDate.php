<?php

namespace App\Support;

/**
 * Hijri (Islamic) calendar helper.
 *
 * Uses the Umm al-Qura algorithm which is the official calendar
 * system for Saudi Arabia and widely used in the UAE for official
 * dates, holidays, and Islamic occasions.
 *
 * Falls back to IntlDateFormatter when intl extension is available;
 * otherwise uses a simplified astronomical approximation.
 */
class HijriDate
{
    /** Format a Gregorian date as Hijri string. */
    public static function fromGregorian(\DateTimeInterface $date, string $locale = 'ar'): string
    {
        if (extension_loaded('intl')) {
            $fmt = new \IntlDateFormatter(
                $locale.'@calendar=islamic-umalqura',
                \IntlDateFormatter::LONG,
                \IntlDateFormatter::NONE,
                $date->getTimezone(),
                \IntlDateFormatter::TRADITIONAL,
            );

            return $fmt->format($date);
        }

        // Fallback: algorithmic approximation (Kuwaiti algorithm)
        $jd = gregoriantojd(
            (int) $date->format('n'),
            (int) $date->format('j'),
            (int) $date->format('Y')
        );

        return self::jdToHijri($jd);
    }

    /** Format as "DD Month YYYY هـ" */
    public static function format(\DateTimeInterface $date, string $locale = 'ar'): string
    {
        $hijri = self::fromGregorian($date, $locale);

        return $hijri.' هـ';
    }

    /** Convert Julian Day to Hijri string (Kuwaiti algorithm). */
    private static function jdToHijri(int $jd): string
    {
        $l = $jd - 1948440 + 10632;
        $n = (int) (($l - 1) / 10631);
        $l = $l - 10631 * $n + 354;
        $j = (int) ((10985 - $l) / 5316) * (int) ((50 * $l) / 17719)
            + (int) ($l / 5670) * (int) ((43 * $l) / 15238);
        $l = $l - (int) ((30 - $j) / 15) * (int) ((17719 * $j) / 50)
            - (int) ($j / 16) * (int) ((15238 * $j) / 43) + 29;
        $m = (int) ((24 * $l) / 709);
        $d = $l - (int) ((709 * $m) / 24);
        $y = 30 * $n + $j - 30;

        $months = [
            1 => 'محرم', 2 => 'صفر', 3 => 'ربيع الأول', 4 => 'ربيع الآخر',
            5 => 'جمادى الأولى', 6 => 'جمادى الآخرة', 7 => 'رجب', 8 => 'شعبان',
            9 => 'رمضان', 10 => 'شوال', 11 => 'ذو القعدة', 12 => 'ذو الحجة',
        ];

        return $d.' '.($months[$m] ?? '').' '.$y;
    }
}
