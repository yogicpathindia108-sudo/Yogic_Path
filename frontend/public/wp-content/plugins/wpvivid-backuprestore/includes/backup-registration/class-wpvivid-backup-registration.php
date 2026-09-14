<?php

if (!defined('WPVIVID_PLUGIN_DIR'))
{
    die;
}

class WPvivid_Backup_Registration
{
    public function register_backup($backup_id, $manifest, $args=array())
    {
        if (!$this->is_valid_backup_id($backup_id))
        {
            return array('result'=>WPVIVID_FAILED, 'error'=>'Invalid backup ID.');
        }

        if (!is_array($manifest) || empty($manifest))
        {
            return array('result'=>WPVIVID_FAILED, 'error'=>'Invalid backup file manifest.');
        }

        $backup_dir=realpath(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.WPvivid_Setting::get_backupdir());
        if ($backup_dir===false || !is_dir($backup_dir))
        {
            return array('result'=>WPVIVID_FAILED, 'error'=>'Failed to get the local backup directory.');
        }

        $normalized_backup_dir=trailingslashit(wp_normalize_path($backup_dir));
        $backup_files=array();
        $seen=array();

        $verify_md5 = isset($args['verify_md5']) ? (bool)$args['verify_md5'] : true;

        foreach ($manifest as $item)
        {
            $ret=$this->validate_file($backup_id, $backup_dir, $normalized_backup_dir, $item, $verify_md5);
            if ($ret['result']!==WPVIVID_SUCCESS)
            {
                return $ret;
            }

            $file_name=$ret['file']['file_name'];
            if (isset($seen[$file_name]))
            {
                return array('result'=>WPVIVID_FAILED, 'error'=>'Duplicate backup file in manifest.');
            }

            $seen[$file_name]=true;
            $backup_files[]=$ret['file'];
        }

        usort($backup_files, function($a, $b)
        {
            return strcmp($a['file_name'], $b['file_name']);
        });

        $backup_result=array(
            'result'=>WPVIVID_SUCCESS,
            'files'=>$backup_files,
        );

        $type=isset($args['type']) && is_string($args['type']) ? $args['type'] : 'Upload';
        $log=isset($args['log']) && is_string($args['log']) ? $args['log'] : '';

        $backup_data=array();
        $backup_data['type']=$type;
        $backup_data['create_time']=$this->get_create_time($backup_files);
        $backup_data['manual_delete']=0;
        $backup_data['local']['path']=WPvivid_Setting::get_backupdir();
        $backup_data['compress']['compress_type']='zip';
        $backup_data['save_local']=1;
        $backup_data['log']=$log;
        $backup_data['backup']=$backup_result;
        $backup_data['remote']=array();
        $backup_data['lock']=0;

        $list_name='wpvivid_backup_list';
        $list_name=apply_filters('get_wpvivid_backup_list_name', $list_name, $backup_id, $backup_data);
        $list=WPvivid_Setting::get_option($list_name);
        if (!is_array($list))
        {
            $list=array();
        }

        if (isset($list[$backup_id]))
        {
            if ($this->has_same_files($list[$backup_id], $backup_files))
            {
                return array(
                    'result'=>WPVIVID_SUCCESS,
                    'backup_id'=>$backup_id,
                    'existing'=>true,
                );
            }

            return array(
                'result'=>WPVIVID_FAILED,
                'error'=>'A different backup with the same ID already exists.',
            );
        }

        $list[$backup_id]=$backup_data;
        WPvivid_Setting::update_option($list_name, $list);

        return array(
            'result'=>WPVIVID_SUCCESS,
            'backup_id'=>$backup_id,
            'existing'=>false,
        );
    }

    private function validate_file($backup_id, $backup_dir, $normalized_backup_dir, $item, $verify_md5)
    {
        if (!is_array($item) || !isset($item['file_name']) || !is_string($item['file_name']))
        {
            return array('result'=>WPVIVID_FAILED, 'error'=>'Invalid backup file information.');
        }

        $file_name=wp_normalize_path($item['file_name']);
        if ($file_name==='' ||
            $file_name==='.' ||
            $file_name==='..' ||
            basename($file_name)!==$file_name ||
            validate_file($file_name)!==0 ||
            preg_match('/\A[a-zA-Z0-9._-]+\z/', $file_name)!==1 ||
            strtolower(pathinfo($file_name, PATHINFO_EXTENSION))!=='zip')
        {
            return array('result'=>WPVIVID_FAILED, 'error'=>'Invalid backup file name.');
        }

        $file_backup_id=$this->get_backup_id_from_file_name($file_name);

        $request_token = $this->get_backup_id_token($backup_id);
        $file_token = $this->get_backup_id_token($file_backup_id);
        if ($request_token === false || $file_token === false || !hash_equals($request_token, $file_token))
        {
            return array(
                'result' => WPVIVID_FAILED,
                'error'  => 'The backup file ID does not match.',
            );
        }

        $file_path=realpath($backup_dir.DIRECTORY_SEPARATOR.$file_name);
        if ($file_path===false || !is_file($file_path) || strpos(wp_normalize_path($file_path), $normalized_backup_dir)!==0)
        {
            return array('result'=>WPVIVID_FAILED, 'error'=>'Backup file is outside the backup directory or does not exist.');
        }

        $actual_size=filesize($file_path);
        if ($actual_size === false)
        {
            return array(
                'result' => WPVIVID_FAILED,
                'error'  => 'Failed to read the backup file.',
            );
        }

        if (isset($item['size']))
        {
            $expected_size=filter_var($item['size'], FILTER_VALIDATE_INT, array('options'=>array('min_range'=>0)));
            if ($expected_size===false || $actual_size!==$expected_size)
            {
                return array('result'=>WPVIVID_FAILED, 'error'=>'Backup file size does not match.');
            }
        }

        $actual_md5 = '';
        if ($verify_md5)
        {
            if (!isset($item['md5']) || !is_string($item['md5']) || preg_match('/\A[a-fA-F0-9]{32}\z/', $item['md5']) !== 1)
            {
                return array(
                    'result' => WPVIVID_FAILED,
                    'error'  => 'Invalid backup file checksum.',
                );
            }

            $actual_md5 = md5_file($file_path);
            if ($actual_md5 === false || !hash_equals(strtolower($item['md5']), strtolower($actual_md5)))
            {
                return array(
                    'result' => WPVIVID_FAILED,
                    'error'  => 'Backup file checksum does not match.',
                );
            }
        }

        $info=WPvivid_Backup_Item::get_backup_file_info($file_path);
        if (!is_array($info) || !isset($info['result']) || $info['result']!==WPVIVID_SUCCESS)
        {
            $error=is_array($info) && isset($info['error']) ? $info['error'] : 'Invalid WPvivid backup file.';
            return array('result'=>WPVIVID_FAILED, 'error'=>$error);
        }

        $file = array(
            'file_name' => $file_name,
            'size'      => $actual_size,
        );

        if ($verify_md5)
        {
            $file['md5'] = $actual_md5;
        }

        return array(
            'result' => WPVIVID_SUCCESS,
            'file'   => $file,
        );
    }

    private function is_valid_backup_id($backup_id)
    {
        return is_string($backup_id) &&
            $backup_id!=='' &&
            $backup_id!=='0' &&
            preg_match('/\A[a-zA-Z0-9_-]+\z/', $backup_id)===1;
    }

    private function get_backup_id_from_file_name($file_name)
    {
        if (!is_string($file_name))
        {
            return false;
        }

        $prefixes = array('wpvivid');

        $white_label_prefix = apply_filters('wpvivid_white_label_file_prefix', 'wpvivid');

        if (is_string($white_label_prefix) && $white_label_prefix !== '')
        {
            $prefixes[] = $white_label_prefix;
        }

        foreach (array_unique($prefixes) as $prefix)
        {
            if (
                preg_match(
                    '/' .
                    preg_quote($prefix, '/') .
                    '-([a-zA-Z0-9]+)_/',
                    $file_name,
                    $matches
                ) === 1
            )
            {
                return 'wpvivid-' . $matches[1];
            }
        }

        if (
            preg_match(
                '/(?:^|_)[^_]+-([a-zA-Z0-9]+)_' .
                '[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}(?:_|$)/',
                $file_name,
                $matches
            ) === 1
        )
        {
            return 'wpvivid-' . $matches[1];
        }

        return false;
    }

    private function get_create_time($files)
    {
        foreach ($files as $file)
        {
            if (preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}/', $file['file_name'], $matches)===1)
            {
                $parts=explode('-', $matches[0]);
                if (count($parts)===5)
                {
                    $time=strtotime($parts[0].'-'.$parts[1].'-'.$parts[2].' '.$parts[3].':'.$parts[4]);
                    if ($time!==false)
                    {
                        return $time;
                    }
                }
            }
        }

        return time();
    }

    private function has_same_files($backup_data, $files)
    {
        if (!isset($backup_data['backup']['files']) || !is_array($backup_data['backup']['files']))
        {
            return false;
        }

        $existing=array();
        foreach ($backup_data['backup']['files'] as $file)
        {
            if (!is_array($file) || !isset($file['file_name'], $file['size']))
            {
                return false;
            }
            $existing[$file['file_name']]=(int)$file['size'];
        }

        $incoming=array();
        foreach ($files as $file)
        {
            $incoming[$file['file_name']]=(int)$file['size'];
        }

        ksort($existing);
        ksort($incoming);
        return $existing===$incoming;
    }

    private function get_backup_id_token($backup_id)
    {
        if (
            !is_string($backup_id) ||
            preg_match(
                '/-([a-zA-Z0-9]+)$/',
                $backup_id,
                $matches
            ) !== 1
        )
        {
            return false;
        }

        return $matches[1];
    }
}
