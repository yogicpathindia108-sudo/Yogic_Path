<?php

if (!defined('WPVIVID_PLUGIN_DIR'))
{
    die;
}

class WPvivid_Option
{
    const DB_VERSION = 1;
    const DB_VERSION_OPTION = 'wpvivid_options_db_version';

    protected static $table_checked = false;
    protected static $table_available = false;
    protected static $cache = array();

    public static function get_table_name()
    {
        global $wpdb;

        return $wpdb->base_prefix . 'wpvivid_options';
    }

    public static function table_exists()
    {
        global $wpdb;

        $table = self::get_table_name();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table
            )
        );

        return $result === $table;
    }

    public static function ensure_table()
    {
        global $wpdb;

        if (self::$table_checked)
        {
            return self::$table_available;
        }

        self::$table_checked = true;

        if (self::table_exists())
        {
            self::$table_available = true;
            return true;
        }

        $table_name = self::get_table_name();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `option_name` varchar(191) NOT NULL DEFAULT '',
            `option_value` longtext NOT NULL,
            PRIMARY KEY (`option_id`),
            UNIQUE KEY `option_name` (`option_name`)
        );";

        $wpdb->query($sql);
        self::$table_available = self::table_exists();

        return self::$table_available;
    }

    public static function get_option($option_name, $default = false)
    {
        global $wpdb;

        if (array_key_exists($option_name, self::$cache))
        {
            return self::$cache[$option_name];
        }

        if (!self::ensure_table())
        {
            return $default;
        }

        $table_name = self::get_table_name();

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM `{$table_name}` WHERE option_name = %s LIMIT 1",
                $option_name
            )
        );

        if ($value === null)
        {
            self::$cache[$option_name] = $default;
            return $default;
        }

        $value = maybe_unserialize($value);
        self::$cache[$option_name] = $value;

        return $value;
    }

    public static function update_option($option_name, $value)
    {
        global $wpdb;

        if (!self::ensure_table())
        {
            return false;
        }

        $table_name = self::get_table_name();
        $serialized = maybe_serialize($value);

        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT option_id FROM `{$table_name}` WHERE option_name = %s LIMIT 1", $option_name)
        );

        if ($exists === null)
        {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'option_name'  => $option_name,
                    'option_value' => $serialized,
                )
            );
        }
        else
        {
            $result = $wpdb->update(
                $table_name,
                array('option_value' => $serialized),
                array('option_id' => $exists)
            );
        }

        if ($result === false)
        {
            return false;
        }

        self::$cache[$option_name] = $value;

        return true;
    }

    public static function clear_cache()
    {
        self::$cache = array();
    }

    public static function option_exists($option_name)
    {
        global $wpdb;

        if (!self::ensure_table())
        {
            return false;
        }

        $table_name = self::get_table_name();

        $option_id = $wpdb->get_var(
            $wpdb->prepare("SELECT option_id FROM {$table_name} WHERE option_name = %s LIMIT 1", $option_name)
        );

        return $option_id !== null;
    }

    public static function sanitize_last_backup_message($task)
    {
        if (!is_array($task))
        {
            return array();
        }

        $message = array();

        if (isset($task['id']))
        {
            $message['id'] = $task['id'];
        }

        $message['status'] = array();

        $status_keys = array(
            'str',
            'start_time',
            'run_time',
            'timeout',
            'error',
        );

        if (isset($task['status']) && is_array($task['status']))
        {
            foreach ($status_keys as $key)
            {
                if (array_key_exists($key, $task['status']))
                {
                    $message['status'][$key] = $task['status'][$key];
                }
            }
        }

        if (isset($task['options']['log_file_name']))
        {
            $message['options'] = array(
                'log_file_name' => $task['options']['log_file_name'],
            );
        }

        return $message;
    }

    private static function migrate_last_backup_message()
    {
        $option_name = 'wpvivid_last_msg';
        $not_found = new stdClass();

        $old_message = get_option($option_name, $not_found);

        if ($old_message !== $not_found)
        {
            if (is_array($old_message) && !empty($old_message))
            {
                $message = self::sanitize_last_backup_message($old_message);

                if (empty($message['id']))
                {
                    $message = array();
                }
            }
            else
            {
                $message = array();
            }

            if (!self::update_option($option_name, $message))
            {
                return false;
            }

            delete_option($option_name);

            return true;
        }

        $current_message = self::get_option($option_name, $not_found);

        if ($current_message === $not_found)
        {
            return true;
        }

        if (!is_array($current_message) || empty($current_message))
        {
            return self::update_option($option_name, array());
        }

        $message = self::sanitize_last_backup_message($current_message);

        if (empty($message['id']))
        {
            $message = array();
        }

        return self::update_option($option_name, $message);
    }

    public static function get_last_backup_message($default = array())
    {
        $message = self::get_option('wpvivid_last_msg', $default);

        return is_array($message) ? $message : $default;
    }

    public static function update_last_backup_message($task)
    {
        $message = self::sanitize_last_backup_message($task);

        if (empty($message['id']))
        {
            return false;
        }

        return self::update_option('wpvivid_last_msg', $message);
    }

    public static function maybe_upgrade($force = false)
    {
        $installed_version = (int) get_site_option(self::DB_VERSION_OPTION, 0);

        if ($force)
        {
            if (!self::ensure_table())
            {
                return false;
            }
        }

        if ($installed_version >= self::DB_VERSION)
        {
            return true;
        }

        if (!self::ensure_table())
        {
            return false;
        }

        for ($version = $installed_version + 1; $version <= self::DB_VERSION; $version++)
        {
            if ($version === 1)
            {
                if (!self::migrate_last_backup_message())
                {
                    return false;
                }
            }

            $result = update_site_option(self::DB_VERSION_OPTION, $version);

            if (!$result)
            {
                return false;
            }

            $installed_version = $version;
        }

        return $installed_version >= self::DB_VERSION;
    }
}
