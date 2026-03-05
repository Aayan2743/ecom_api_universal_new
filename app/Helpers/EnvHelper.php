<?php
namespace App\Helpers;

class EnvHelper
{
    public static function setEnvValue($key, $value)
    {
        $path = base_path('.env');

        if (! file_exists($path)) {
            return false;
        }

        $env = file_get_contents($path);

        $key = strtoupper($key);

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        if (! in_array($value, ['true', 'false'])) {
            $value = '"' . str_replace('"', '\"', $value ?? '') . '"';
        }

        $pattern = "/^{$key}=.*/m";

        if (preg_match($pattern, $env)) {
            $env = preg_replace($pattern, "{$key}={$value}", $env);
        } else {
            $env .= PHP_EOL . "{$key}={$value}";
        }

        file_put_contents($path, $env);

        return true;
    }
}
