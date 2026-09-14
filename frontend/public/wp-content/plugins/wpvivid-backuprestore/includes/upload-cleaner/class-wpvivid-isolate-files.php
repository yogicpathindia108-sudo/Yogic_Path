<?php

if (!defined('WPVIVID_PLUGIN_DIR'))
{
    die;
}

class WPvivid_Isolate_Files
{
    public function __construct()
    {

    }

    private function normalize_relative_path($path)
    {
        if(!is_string($path) || $path==='' || strpos($path, "\0")!==false)
        {
            return false;
        }

        $path=str_replace('\\','/',$path);
        if($path==='' || $path[0]==='/' || preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#',$path))
        {
            return false;
        }

        $parts=explode('/',$path);
        foreach($parts as $part)
        {
            if($part==='' || $part==='.' || $part==='..')
            {
                return false;
            }
        }

        return implode(DIRECTORY_SEPARATOR,$parts);
    }

    private function is_path_inside($path,$root)
    {
        $path=wp_normalize_path($path);
        $root=trailingslashit(wp_normalize_path($root));

        if(DIRECTORY_SEPARATOR==='\\')
        {
            $path=strtolower($path);
            $root=strtolower($root);
        }

        return strpos($path,$root)===0;
    }

    private function get_existing_path($root,$relative_path)
    {
        $relative_path=$this->normalize_relative_path($relative_path);
        $real_root=realpath($root);
        if($relative_path===false || $real_root===false)
        {
            return false;
        }

        $candidate=$real_root.DIRECTORY_SEPARATOR.$relative_path;
        $real_candidate=realpath($candidate);
        if($real_candidate===false || !$this->is_path_inside($real_candidate,$real_root))
        {
            return false;
        }

        return $candidate;
    }

    private function get_safe_destination($root,$relative_path)
    {
        $relative_path=$this->normalize_relative_path($relative_path);
        $real_root=realpath($root);
        if($relative_path===false || $real_root===false)
        {
            return false;
        }

        $parts=explode(DIRECTORY_SEPARATOR,$relative_path);
        array_pop($parts);
        $current=$real_root;

        foreach($parts as $part)
        {
            $current.=DIRECTORY_SEPARATOR.$part;
            if(file_exists($current) || is_link($current))
            {
                $real_current=realpath($current);
                if($real_current===false || !is_dir($real_current) || !$this->is_path_inside($real_current,$real_root))
                {
                    return false;
                }
                $current=$real_current;
            }
            else if(!@mkdir($current,0777) && !is_dir($current))
            {
                return false;
            }
        }

        $candidate=$real_root.DIRECTORY_SEPARATOR.$relative_path;
        if(file_exists($candidate) || is_link($candidate))
        {
            $real_candidate=realpath($candidate);
            if($real_candidate===false || !$this->is_path_inside($real_candidate,$real_root))
            {
                return false;
            }
        }

        return $candidate;
    }

    public function check_folder()
    {
        if(!is_dir(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.WPVIVID_UPLOADS_ISO_DIR))
        {
            @mkdir(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.WPVIVID_UPLOADS_ISO_DIR,0777,true);
        }
    }

    public function isolate_files($files)
    {
        $upload_dir=wp_upload_dir();
        $root_path=$upload_dir['basedir'].DIRECTORY_SEPARATOR;

        if(!is_dir(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.WPVIVID_UPLOADS_ISO_DIR))
        {
            @mkdir(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.WPVIVID_UPLOADS_ISO_DIR,0777,true);
        }

        $iso_dir=WP_CONTENT_DIR.DIRECTORY_SEPARATOR.WPVIVID_UPLOADS_ISO_DIR.DIRECTORY_SEPARATOR;

        foreach ($files as $file)
        {
            $from=$this->get_existing_path($root_path,$file);
            if($from!==false && file_exists($from))
            {
                $to=$this->get_safe_destination($iso_dir,$file);
                if($to!==false)
                {
                    @rename($from,$to);
                }
            }
        }
        $ret['result']='success';
        return $ret;
    }

    public function init_isolate_task()
    {
        $task['start_time']=time();
        $task['running_time']=time();
        $task['status']='running';
        $task['progress']=0;
        $task['offset']=0;

        update_option('init_isolate_task',$task,'no');
    }

    public function get_isolate_task_offset()
    {
        $task=get_option('scan_unused_files_task',array());
        if(empty($task))
        {
            return false;
        }

        if($task['status']=='finished')
        {
            return false;
        }

        return $task['offset'];
    }

    public function update_isolate_task($offset,$status='running',$progress=0)
    {
        $task=get_option('scan_unused_files_task',array());

        $task['running_time']=time();
        $task['status']=$status;
        $task['progress']=$progress;
        $task['offset']=$offset;

        update_option('scan_unused_files_task',$task,'no');
    }

    public function get_isolate_folder()
    {
        $root=WP_CONTENT_DIR.DIRECTORY_SEPARATOR.WPVIVID_UPLOADS_ISO_DIR;
        $ret=$this->get_folder_list($root);
        return $ret;
    }

    public function get_isolate_files($search='',$folder_ex='',$count=0)
    {
        $root=WP_CONTENT_DIR.DIRECTORY_SEPARATOR.WPVIVID_UPLOADS_ISO_DIR;
        $ret=$this->get_folder_list($root,$search);

        if(empty($folder_ex))
        {
            $result=$ret['files'];
            foreach ($ret['folders'] as $folder)
            {
                $files=array();
                $this->scan_list_uploaded_files($files,$root.DIRECTORY_SEPARATOR.$folder,$root,$folder,$search);
                $result=array_merge($result,$files);
            }
        }
        else if($folder_ex=='.')
        {
            $result=$ret['files'];

            if($count>0&&sizeof($result)>$count)
            {
                $result=array_slice($result, 0, $count);
            }
            return $result;
        }
        else
        {
            $safe_folder=$this->normalize_relative_path($folder_ex);
            if($safe_folder===false)
            {
                return array();
            }
            $files=array();
            $this->scan_list_uploaded_files($files,$root.DIRECTORY_SEPARATOR.$safe_folder,$root,$safe_folder,$search);
            $result=$files;
        }

        if($count>0&&sizeof($result)>$count)
        {
            $result=array_slice($result, 0, $count);
        }

        return $result;
    }

    private function get_folder_list($root_path,$search='')
    {
        $result['folders']=array();
        $result['folders'][]='root';
        $result['files']=array();
        if(!file_exists($root_path)){
            @mkdir($root_path, 0755, true);
        }
        $handler = opendir($root_path);
        if($handler!==false)
        {
            while (($filename = readdir($handler)) !== false)
            {
                if ($filename != "." && $filename != "..")
                {
                    if (is_dir($root_path . DIRECTORY_SEPARATOR . $filename))
                    {
                        if(preg_match('#^\d{4}$#',$filename))
                        {
                            $result['folders']=array_merge( $result['folders'],$this->get_sub_folder($root_path . DIRECTORY_SEPARATOR . $filename,$filename));
                        }
                        else
                        {
                            $ret=scandir($root_path . DIRECTORY_SEPARATOR . $filename);
                            if($ret!==false&&count($ret)!=2)
                            {
                                $result['folders'][]=$filename;
                            }

                        }

                    } else {

                        if($filename=='.htaccess'||$filename=='index.php')
                        {
                            continue;
                        }

                        if(empty($search))
                        {
                            $file['path']=$filename;
                            $file['folder']='.';
                            $result['files'][] = $file;
                        }
                        else if(preg_match('#'.$search.'#',$filename))
                        {
                            $file['path']=$filename;
                            $file['folder']='.';
                            $result['files'][] = $file;
                        }
                    }
                }
            }
            if($handler)
                @closedir($handler);
        }

        return $result;
    }

    private function get_sub_folder($path,$root)
    {
        $folders=array();
        $handler = opendir($path);
        if($handler!==false)
        {
            while (($filename = readdir($handler)) !== false)
            {
                if ($filename != "." && $filename != "..")
                {
                    if (is_dir($path . DIRECTORY_SEPARATOR . $filename))
                    {
                        $ret=scandir($path . DIRECTORY_SEPARATOR . $filename);
                        if($ret!==false&&count($ret)!=2)
                        {
                            $folders[]=$root.DIRECTORY_SEPARATOR.$filename;
                        }
                    }
                }
            }
            if($handler)
                @closedir($handler);
        }
        return $folders;
    }

    private function scan_list_uploaded_files( &$files,$path,$root,$folder,$search='')
    {
        $count = 0;
        if(is_dir($path))
        {
            $handler = opendir($path);
            if($handler!==false)
            {
                while (($filename = readdir($handler)) !== false)
                {
                    if ($filename != "." && $filename != "..")
                    {
                        $count++;

                        if (is_dir($path . DIRECTORY_SEPARATOR . $filename))
                        {
                            $this->scan_list_uploaded_files($files, $path . DIRECTORY_SEPARATOR . $filename,$root,$folder,$search);
                        } else {
                            if(empty($search))
                            {
                                $file['path']=str_replace($root . DIRECTORY_SEPARATOR,'',$path . DIRECTORY_SEPARATOR . $filename);
                                $file['folder']=$folder;
                                $files[] = $file;
                            }
                            else if(preg_match('#'.$search.'#',$filename))
                            {
                                $file['path']=str_replace($root . DIRECTORY_SEPARATOR,'',$path . DIRECTORY_SEPARATOR . $filename);
                                $file['folder']=$folder;
                                $files[] = $file;
                            }
                        }
                    }
                }
                if($handler)
                    @closedir($handler);
            }
        }

        return $files;
    }

    public function delete_files($files)
    {
        $root=WP_CONTENT_DIR.DIRECTORY_SEPARATOR.WPVIVID_UPLOADS_ISO_DIR;
        $delete_media_when_delete_file=get_option('wpvivid_uc_delete_media_when_delete_file',true);
        foreach ($files as $file)
        {
            $path=$this->get_existing_path($root,$file);
            if($path===false || !is_file($path))
            {
                continue;
            }
            @wp_delete_file($path);

            if($delete_media_when_delete_file)
            {
                $attachment_id=$this->find_media_id_from_file($file);
                if($attachment_id)
                {
                    wp_delete_attachment( $attachment_id, true );
                }
            }
        }
    }

    public function delete_files_ex($files)
    {
        $root=WP_CONTENT_DIR.DIRECTORY_SEPARATOR.WPVIVID_UPLOADS_ISO_DIR;
        $delete_media_when_delete_file=get_option('wpvivid_uc_delete_media_when_delete_file',true);
        foreach ($files as $file)
        {
            if(!isset($file['path']))
            {
                continue;
            }
            $path=$this->get_existing_path($root,$file['path']);
            if($path===false || !is_file($path))
            {
                continue;
            }
            @wp_delete_file($path);

            if($delete_media_when_delete_file)
            {
                $attachment_id=$this->find_media_id_from_file($file['path']);
                if($attachment_id)
                {
                    wp_delete_attachment( $attachment_id, true );
                }
            }
        }
    }

    public function find_media_id_from_file( $file )
    {
        global $wpdb;

        $file=basename((string)$file);

        $sql = $wpdb->prepare(
            "SELECT post_id
			FROM {$wpdb->postmeta}
			WHERE meta_key = %s
			AND meta_value LIKE %s",
            '_wp_attachment_metadata',
            '%'.$wpdb->esc_like($file).'%'
        );
        $ret = $wpdb->get_var( $sql );
        if(!$ret)
        {
            $sql = $wpdb->prepare( "SELECT post_id
			FROM {$wpdb->postmeta}
			WHERE meta_key = %s
			AND meta_value = %s", '_wp_attached_file', $file
            );
            $ret = $wpdb->get_var( $sql );
        }
        return $ret;
    }

    public function restore_files($files)
    {
        $upload_dir=wp_upload_dir();

        $root_path=WP_CONTENT_DIR.DIRECTORY_SEPARATOR.WPVIVID_UPLOADS_ISO_DIR.DIRECTORY_SEPARATOR;
        $upload_path=$upload_dir['basedir'].DIRECTORY_SEPARATOR;

        foreach ($files as $file)
        {
            $from=$this->get_existing_path($root_path,$file);
            if($from!==false && file_exists($from))
            {
                $to=$this->get_safe_destination($upload_path,$file);
                if($to!==false)
                {
                    @rename($from,$to);
                }
            }
        }
        $ret['result']='success';
        return $ret;
    }

    public function restore_files_ex($files)
    {
        $upload_dir=wp_upload_dir();

        $root_path=WP_CONTENT_DIR.DIRECTORY_SEPARATOR.WPVIVID_UPLOADS_ISO_DIR.DIRECTORY_SEPARATOR;
        $upload_path=$upload_dir['basedir'].DIRECTORY_SEPARATOR;

        foreach ($files as $file)
        {
            if(!isset($file['path']))
            {
                continue;
            }
            $from=$this->get_existing_path($root_path,$file['path']);
            if($from!==false && file_exists($from))
            {
                $to=$this->get_safe_destination($upload_path,$file['path']);
                if($to!==false)
                {
                    @rename($from,$to);
                }
            }
        }
        $ret['result']='success';
        return $ret;
    }
}