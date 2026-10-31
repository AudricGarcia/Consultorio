<?php

namespace App\Support;

class DentalFdi
{
    /** @return list<string> */
    public static function toothCodes(): array
    {
        $codes = [];

        foreach ([1, 2, 3, 4] as $quadrant) {
            foreach (range(1, 8) as $tooth) {
                $codes[] = (string) ($quadrant.$tooth);
            }
        }

        foreach ([5, 6, 7, 8] as $quadrant) {
            foreach (range(1, 5) as $tooth) {
                $codes[] = (string) ($quadrant.$tooth);
            }
        }

        return $codes;
    }

    /** @return list<string> */
    public static function surfaces(): array
    {
        return ['occlusal', 'mesial', 'distal', 'vestibular', 'lingual', 'palatal', 'incisal'];
    }
}
