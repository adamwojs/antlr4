<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Misc;

final class IdentityHashMap
{
    /** @var array */
    private $identityMap = [];

    /**
     * Tests whether the specified object reference is a key in this identity
     * hash map.
     *
     * @param object $key
     *
     * @return bool
     */
    public function containsKey($key): bool
    {
        return isset($this->identityMap[spl_object_id($key)]);
    }

    /**
     * Returns the value to which the specified key is mapped,
     * or {@code null} if this map contains no mapping for the key.
     *
     * @param object $key
     *
     * @return object|null
     */
    public function get($key)
    {
        if ($this->containsKey($key)) {
            return $this->identityMap[spl_object_id($key)];
        }

        return null;
    }

    /**
     * Associates a key with a value, replacing a previous association if there
     * was one.
     *
     * @param object $key
     * @param object|null $value
     */
    public function put($key, $value): void
    {
        $this->identityMap[spl_object_id($key)] = $value;
    }

    /**
     * Removes the mapping for this key from this map if present.
     *
     * @param object $key
     */
    public function remove(object  $key): void
    {
        if ($this->containsKey($key)) {
            unset($this->identityMap[spl_object_id($key)]);
        }
    }
}
