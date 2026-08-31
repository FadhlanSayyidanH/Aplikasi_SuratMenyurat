<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

/**
 * Helper kecil dipakai bareng oleh BagController/BagMemberController/
 * BagMasukController supaya validasi input di sini SAMA PERSIS semantiknya
 * dengan proyek PHP lama (filter_var FILTER_VALIDATE_INT, cast (bool), dan
 * operator null-coalescing `??`) -- bukan validasi Laravel yang lebih ketat/
 * berbeda perilaku pada tepian kasus (mis. "0", string desimal, dst).
 */
trait ValidatesLikeOldPhp
{
    /**
     * Setara `$array[$key] ?? $default` PHP lama -- null (baik karena key
     * tidak dikirim maupun dikirim eksplisit sebagai JSON null) dianggap
     * sama, keduanya jatuh ke $default.
     */
    protected function inputOrDefault(Request $request, string $key, mixed $default): mixed
    {
        $value = $request->input($key);

        return $value === null ? $default : $value;
    }

    /**
     * Setara filter_var($value, FILTER_VALIDATE_INT) proyek PHP lama --
     * bilangan bulat (boleh string berisi digit dengan spasi pinggir/tanda
     * +/-) dianggap valid; selain itu (desimal non-bulat, bukan-numerik,
     * bool, array, null) mengembalikan false.
     */
    protected function phpValidateInt(mixed $value): int|false
    {
        if (is_int($value)) {
            return $value;
        }
        if ($value === null || is_bool($value) || is_array($value)) {
            return false;
        }
        if (is_float($value)) {
            return $value == (int) $value ? (int) $value : false;
        }
        $str = trim((string) $value);
        if ($str === '' || !preg_match('/^[+-]?\d+$/', $str)) {
            return false;
        }

        return (int) $str;
    }

    /**
     * Setara cast (bool) PHP lama -- 0, 0.0, "", "0", array kosong, null
     * dianggap false, selain itu true. $default dipakai kalau nilainya null
     * (persis `?? $default` sebelum di-cast ke bool, pola dipakai
     * bag_create.php/bag_update.php untuk `untuk_keluar`).
     */
    protected function phpBool(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }
        if (is_string($value)) {
            return !in_array($value, ['', '0'], true);
        }
        if (is_array($value)) {
            return count($value) > 0;
        }

        return (bool) $value;
    }
}
