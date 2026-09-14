<?php

if (!defined('WPVIVID_PLUGIN_DIR'))
{
    die;
}

class WPvivid_Extract_Security
{
    public static function begin($restricted_root)
    {
        unset($GLOBALS['wpvivid_extract_security_root']);
        unset($GLOBALS['wpvivid_extract_security_failed']);
        unset($GLOBALS['wpvivid_extract_security_error']);

        if (is_string($restricted_root) && $restricted_root !== '')
        {
            $GLOBALS['wpvivid_extract_security_root'] = untrailingslashit($restricted_root);
        }
    }

    public static function validate($target_path)
    {
        if (!isset($GLOBALS['wpvivid_extract_security_root']) ||
            $GLOBALS['wpvivid_extract_security_root'] === '')
        {
            self::fail('The extraction root is missing.');
            return false;
        }

        $restricted_root = $GLOBALS['wpvivid_extract_security_root'];
        if (!is_string($target_path) || $target_path === '')
        {
            self::fail('The extraction target is invalid.');
            return false;
        }

        $normalized_root = WPvivid_PclZipUtilNormalizePath($restricted_root);
        $normalized_target = WPvivid_PclZipUtilNormalizePath($target_path);

        if ($normalized_root === false || $normalized_target === false ||
            !WPvivid_PclZipUtilIsPathInside($normalized_root, $normalized_target) ||
            !WPvivid_PclZipUtilHasSafeSymlinkPath($normalized_root, $normalized_target))
        {
            self::fail("Filename ".$target_path." is outside the permitted extraction directory.");
            return false;
        }

        return true;
    }

    public static function failed()
    {
        return !empty($GLOBALS['wpvivid_extract_security_failed']);
    }

    public static function error()
    {
        return isset($GLOBALS['wpvivid_extract_security_error'])
            ? $GLOBALS['wpvivid_extract_security_error']
            : '';
    }

    public static function end()
    {
        unset($GLOBALS['wpvivid_extract_security_root']);
        unset($GLOBALS['wpvivid_extract_security_failed']);
        unset($GLOBALS['wpvivid_extract_security_error']);
    }

    private static function fail($error)
    {
        $GLOBALS['wpvivid_extract_security_failed'] = true;
        $GLOBALS['wpvivid_extract_security_error'] = $error;
    }
}

function wpvivid_function_pre_extract_security_callback($p_event, &$p_header)
{
    return WPvivid_Extract_Security::validate($p_header['filename']) ? 1 : 2;
}
