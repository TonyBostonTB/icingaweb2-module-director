<?php

namespace Icinga\Module\Director;

class PlainObjectRenderer
{
    const INDENTATION = '  ';

    public static function render($object)
    {
        return self::renderObject($object);
    }

    protected static function renderBoolean($value)
    {
        return $value ? 'true' : 'false';
    }

    protected static function renderInteger($value)
    {
        return (string) $value;
    }

    protected static function renderFloat($value)
    {
        // Render .0000 floats as integers, mainly because of some JSON
        // implementations:
        if ((string) (int) $value === (string) $value) {
            return static::renderInteger((int) $value);
        } else {
            return sprintf('%F', $value);
        }
    }

    protected static function renderNull()
    {
        return 'null';
    }

    protected static function renderString($value)
    {
        return '"' . addslashes($value) . '"';
    }

    protected static function renderArray($array, $prefix = '')
    {
        if (empty($array)) {
            return '[]';
        }

        $vals = array();

        foreach ($array as $val) {
            $vals[] = $prefix
                    . self::INDENTATION
                    . self::renderObject($val, $prefix . self::INDENTATION);
        }
        return "[\n" . implode(",\n", $vals) . "\n$prefix]";
    }

    protected static function renderHash($hash, $prefix = '')
    {
        $vals = array();
        $hash = (array) $hash;
        if (empty($hash)) {
            return '{}';
        }

        ksort($hash);
        foreach ($hash as $key => $val) {
            $vals[] = $prefix
                    . self::INDENTATION
                    . $key
                    . ': '
                    . self::renderObject($val, $prefix . self::INDENTATION);
        }
        return "{\n" . implode(",\n", $vals) . "\n$prefix}";
    }

    protected static function renderObject($object, $prefix = '')
    {
        if (is_null($object)) {
            return self::renderNull();
        } elseif (is_bool($object)) {
            return self::renderBoolean($object);
        } elseif (is_integer($object)) {
            return self::renderInteger($object);
        } elseif (is_float($object)) {
            return self::renderFloat($object);
        } elseif (is_array($object)) {
            return self::renderArray($object, $prefix);
        } elseif (is_object($object)) {
            return self::renderHash($object, $prefix);
        } elseif (is_string($object)) {
            return self::renderString($object);
        } else {
            return '(UNKNOWN TYPE) ' . var_export($object, 1);
        }
    }
}
