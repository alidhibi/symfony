<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Helper for filtering out properties in casters.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final
 */
class Caster
{
    final const EXCLUDE_VERBOSE = 1;

    final const EXCLUDE_VIRTUAL = 2;

    final const EXCLUDE_DYNAMIC = 4;

    final const EXCLUDE_PUBLIC = 8;

    final const EXCLUDE_PROTECTED = 16;

    final const EXCLUDE_PRIVATE = 32;

    final const EXCLUDE_NULL = 64;

    final const EXCLUDE_EMPTY = 128;

    final const EXCLUDE_NOT_IMPORTANT = 256;

    final const EXCLUDE_STRICT = 512;

    final const PREFIX_VIRTUAL = "\0~\0";

    final const PREFIX_DYNAMIC = "\0+\0";

    final const PREFIX_PROTECTED = "\0*\0";

    /**
     * Casts objects to arrays and adds the dynamic property prefix.
     *
     * @param object $obj          The object to cast
     * @param string $class        The class of the object
     * @param bool   $hasDebugInfo Whether the __debugInfo method exists on $obj or not
     *
     * @return array The array-cast of the object, with prefixed dynamic properties
     */
    public static function castObject($obj, $class, $hasDebugInfo = false, $debugClass = null): array
    {
        if ($class instanceof \ReflectionClass) {
            @trigger_error(sprintf('Passing a ReflectionClass to "%s()" is deprecated since Symfony 3.3 and will be unsupported in 4.0. Pass the class name as string instead.', __METHOD__), \E_USER_DEPRECATED);
            $hasDebugInfo = $class->hasMethod('__debugInfo');
            $class = $class->name;
        }

        if ($hasDebugInfo) {
            try {
                $debugInfo = $obj->__debugInfo();
            } catch (\Exception $e) {
                // ignore failing __debugInfo()
                $hasDebugInfo = false;
            }
        }

        $a = $obj instanceof \Closure ? [] : (array) $obj;

        if ($obj instanceof \__PHP_Incomplete_Class) {
            return $a;
        }

        if ($a !== []) {
            static $publicProperties = [];
            if (null === $debugClass) {
                $debugClass = get_debug_type($obj);
            }

            $i = 0;
            $prefixedKeys = [];
            foreach (array_keys($a) as $k) {
                if (isset($k[0]) ? "\0" !== $k[0] : \PHP_VERSION_ID >= 70200) {
                    if (!isset($publicProperties[$class])) {
                        foreach ((new \ReflectionClass($class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
                            $publicProperties[$class][$prop->name] = true;
                        }
                    }

                    if (!isset($publicProperties[$class][$k])) {
                        $prefixedKeys[$i] = self::PREFIX_DYNAMIC.$k;
                    }
                } elseif ($debugClass !== $class && 1 === strpos($k, $class)) {
                    $prefixedKeys[$i] = "\0".$debugClass.strrchr($k, "\0");
                }

                ++$i;
            }

            if ($prefixedKeys !== []) {
                $keys = array_keys($a);
                foreach ($prefixedKeys as $i => $k) {
                    $keys[$i] = $k;
                }

                $a = array_combine($keys, $a);
            }
        }

        if ($hasDebugInfo && \is_array($debugInfo)) {
            foreach ($debugInfo as $k => $v) {
                if (!isset($k[0]) || "\0" !== $k[0]) {
                    if (\array_key_exists(self::PREFIX_DYNAMIC.$k, $a)) {
                        continue;
                    }

                    $k = self::PREFIX_VIRTUAL.$k;
                }

                unset($a[$k]);
                $a[$k] = $v;
            }
        }

        return $a;
    }

    /**
     * Filters out the specified properties.
     *
     * By default, a single match in the $filter bit field filters properties out, following an "or" logic.
     * When EXCLUDE_STRICT is set, an "and" logic is applied: all bits must match for a property to be removed.
     *
     * @param array    $a                The array containing the properties to filter
     * @param int      $filter           A bit field of Caster::EXCLUDE_* constants specifying which properties to filter out
     * @param string[] $listedProperties List of properties to exclude when Caster::EXCLUDE_VERBOSE is set, and to preserve when Caster::EXCLUDE_NOT_IMPORTANT is set
     * @param int      &$count           Set to the number of removed properties
     *
     * @return array The filtered array
     */
    public static function filter(array $a, $filter, array $listedProperties = [], &$count = 0): array
    {
        $count = 0;

        foreach ($a as $k => $v) {
            $type = self::EXCLUDE_STRICT & $filter;

            if (null === $v) {
                $type |= self::EXCLUDE_NULL & $filter;
                $type |= self::EXCLUDE_EMPTY & $filter;
            } elseif (false === $v || '' === $v || '0' === $v || 0 === $v || 0.0 === $v || [] === $v) {
                $type |= self::EXCLUDE_EMPTY & $filter;
            }

            if ((self::EXCLUDE_NOT_IMPORTANT & $filter) && !\in_array($k, $listedProperties, true)) {
                $type |= self::EXCLUDE_NOT_IMPORTANT;
            }

            if ((self::EXCLUDE_VERBOSE & $filter) && \in_array($k, $listedProperties, true)) {
                $type |= self::EXCLUDE_VERBOSE;
            }

            if (!isset($k[1]) || "\0" !== $k[0]) {
                $type |= self::EXCLUDE_PUBLIC & $filter;
            } elseif ('~' === $k[1]) {
                $type |= self::EXCLUDE_VIRTUAL & $filter;
            } elseif ('+' === $k[1]) {
                $type |= self::EXCLUDE_DYNAMIC & $filter;
            } elseif ('*' === $k[1]) {
                $type |= self::EXCLUDE_PROTECTED & $filter;
            } else {
                $type |= self::EXCLUDE_PRIVATE & $filter;
            }

            if (((self::EXCLUDE_STRICT & $filter) !== 0) ? $type === $filter : $type) {
                unset($a[$k]);
                ++$count;
            }
        }

        return $a;
    }

    public static function castPhpIncompleteClass(\__PHP_Incomplete_Class $c, array $a, Stub $stub, $isNested): array
    {
        if (isset($a['__PHP_Incomplete_Class_Name'])) {
            $stub->class .= '('.$a['__PHP_Incomplete_Class_Name'].')';
            unset($a['__PHP_Incomplete_Class_Name']);
        }

        return $a;
    }
}
