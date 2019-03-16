<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Misc;

final class MurmurHash
{
    private const DEFAULT_SEED = 0;

    private function __construct()
    {
    }

    /**
     * Initialize the hash using the specified {@code seed}.
     *
     * @param int|null $seed the seed
     *
     * @return int the intermediate hash value
     */
    public static function initialize(?int $seed = null): int
    {
        if ($seed === null) {
            $seed = self::DEFAULT_SEED;
        }

        return $seed;
    }

    /**
     * Update the intermediate hash value for the next input {@code value}.
     *
     * @param int $hash the intermediate hash value
     * @param int $value the value to add to the current hash
     *
     * @return int the updated intermediate hash value
     */
    public static function update(int $hash, int $value): int
    {
        $r1 = 15;
        $r2 = 13;
        $m = 5;

        $k = gmp_mul($value, 0xCC9E2D51);
        $k = gmp_and($k, 0xFFFFFFFF);
        $k = gmp_mul($k, gmp_pow(2, $r1));
        $k = gmp_or($k, self::urshift($k, 32 - $r1));
        $k = gmp_mul($k, 0x1B873593);
        $k = gmp_and($k, 0xFFFFFFFF);

        $hash = gmp_xor($hash, $k);
        $hash = gmp_mul($hash, gmp_pow(2, $r2));
        $hash = gmp_and($hash, 0xFFFFFFFF);
        $hash = gmp_or($hash, self::urshift($hash, 32 - $r2));
        $hash = gmp_mul($hash, $m);
        $hash = gmp_and($hash, 0xFFFFFFFF);
        $hash = gmp_mul($hash, 0xE6546B64);
        $hash = gmp_and($hash, 0xFFFFFFFF);

        return (int)$hash;
    }

    /**
     * Apply the final computation steps to the intermediate value {@code hash}
     * to form the final result of the MurmurHash 3 hash function.
     *
     * @param int $hash the intermediate hash value
     * @param int $numberOfWords the number of integer values added to the hash
     *
     * @return int the final hash result
     */
    public static function finish(int $hash, int $numberOfWords): int
    {
        $hash = gmp_xor($hash, $numberOfWords * 4);
        $hash = gmp_xor($hash, self::urshift($hash, 16));
        $hash = gmp_mul($hash, 0x85EBCA6B);
        $hash = gmp_and($hash, 0xFFFFFFFF);
        $hash = gmp_xor($hash, self::urshift($hash, 13));
        $hash = gmp_mul($hash, 0xC2B2AE35);
        $hash = gmp_and($hash, 0xFFFFFFFF);
        $hash = gmp_xor($hash, self::urshift($hash, 16));

        return (int) gmp_strval($hash, 10);
    }

    /**
     * Utility function to compute the hash code of an array using the
     * MurmurHash algorithm.
     *
     * @param int[] $data the array data
     * @param int|null $seed the seed for the MurmurHash algorithm
     *
     * @return int the hash code of the data
     */
    public static function hashOfArray(array $data, ?int $seed = null): int
    {
        $hash = self::initialize($seed);
        foreach ($data as $value) {
            $hash = self::update($hash, $value);
        }

        return self::finish($hash, count($data));
    }

    /**
     * Unsigned right shift.
     */
    private static function urshift($a, $b)
    {
        return gmp_div($a, gmp_pow(2, $b));
    }
}
