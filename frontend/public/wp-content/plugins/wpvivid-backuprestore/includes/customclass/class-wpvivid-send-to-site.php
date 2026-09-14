<?php

if (!defined('WPVIVID_PLUGIN_DIR'))
{
    die;
}

if(!defined('WPVIVID_REMOTE_SEND_TO_SITE'))
    define('WPVIVID_REMOTE_SEND_TO_SITE','send_to_site');
include_once WPVIVID_PLUGIN_DIR . '/includes/customclass/class-wpvivid-remote.php';

if(!defined('WPVIVID_SEND_TO_SITE_UPLOAD_SIZE'))
    define('WPVIVID_SEND_TO_SITE_UPLOAD_SIZE', 2);

class WPvivid_Send_to_site extends WPvivid_Remote
{
    public $options;

    public function __construct($options=array())
    {
        if(empty($options))
        {
            if(!defined('WPVIVID_INIT_SEND_TO_SITE'))
            {
                add_action('plugins_loaded', array($this, 'plugins_loaded'));

                define('WPVIVID_INIT_SEND_TO_SITE',1);
            }
        }
        else
        {
            $this->options=$options;
        }
    }

    public function plugins_loaded()
    {
        if (empty($_POST) || !isset($_POST['wpvivid_action']) || !is_string($_POST['wpvivid_action']))
        {
            return;
        }

        @ini_set('display_errors', 0);

        $action = sanitize_key(wp_unslash($_POST['wpvivid_action']));

        $allowed_actions = array(
            'send_to_site_connect',
            'send_to_site_finish',
            'send_to_site',
            'send_to_site_file_status',
            'clear_backup_cache',
        );

        if (!in_array($action, $allowed_actions, true))
        {
            return;
        }

        /*
         * The client version is sent outside the encrypted payload so that an
         * outdated client can be rejected before RSA decryption is attempted.
         * This check is only used to provide an upgrade message. It never
         * replaces request authentication.
         */
        $version_check = $this->check_migration_client_version();
        if ($version_check['result'] !== WPVIVID_SUCCESS)
        {
            status_header(426);
            echo wp_json_encode($version_check);
            die();
        }

        /*
         * Authentication must happen before RSA decryption.
         */
        if (!$this->verify_request_auth($action))
        {
            status_header(403);

            echo wp_json_encode(array(
                'result' => WPVIVID_FAILED,
                'error'  => 'Migration request authentication failed.',
            ));

            die();
        }

        switch ($action)
        {
            case 'send_to_site_connect':
                $this->send_to_site_connect();
                break;

            case 'send_to_site_finish':
                $this->send_to_site_finish();
                break;

            case 'send_to_site':
                $this->send_to_site();
                break;

            case 'send_to_site_file_status':
                $this->send_to_site_file_status();
                break;

            case 'clear_backup_cache':
                $this->clear_backup_cache();
                break;
        }

        die();
    }

    public function init_remotes($remote_collection)
    {
        $remote_collection[WPVIVID_REMOTE_SEND_TO_SITE] = 'WPvivid_Send_to_site';
        return $remote_collection;
    }

    public function test_connect()
    {
        return array('result' => WPVIVID_SUCCESS,'test'=>$this->options['url']);
    }

    public function upload($task_id, $files, $callback = '')
    {
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-crypt.php';

        global $wpvivid_plugin;
        $wpvivid_plugin->wpvivid_log->WriteLog('Connect site ','notice');
        $ret=$this->connect_site($task_id);
        if($ret['result']==WPVIVID_FAILED)
        {
            if($ret['error']=='The uploading backup already exists in Backups list.')
            {
                return array('result' =>WPVIVID_SUCCESS);
            }
            else
            {
                return $ret;
            }
        }
        $upload_job=WPvivid_taskmanager::get_backup_sub_task_progress($task_id,'upload',WPVIVID_REMOTE_SEND_TO_SITE);
        if(empty($upload_job))
        {
            $job_data=array();
            foreach ($files as $file)
            {
                $file_data['size']=filesize($file);
                $file_data['uploaded']=0;
                $job_data[basename($file)]=$file_data;
            }
            WPvivid_taskmanager::update_backup_sub_task_progress($task_id,'upload',WPVIVID_REMOTE_SEND_TO_SITE,WPVIVID_UPLOAD_UNDO,'Start uploading',$job_data);
            $upload_job=WPvivid_taskmanager::get_backup_sub_task_progress($task_id,'upload',WPVIVID_REMOTE_SEND_TO_SITE);
        }

        foreach ($files as $file)
        {
            $wpvivid_plugin->set_time_limit($task_id);
            if(array_key_exists(basename($file),$upload_job['job_data']))
            {
                if($upload_job['job_data'][basename($file)]['uploaded']==1)
                    continue;
            }

            $this -> last_time = time();
            $this -> last_size = 0;

            if(!file_exists($file))
                return array('result' =>WPVIVID_FAILED,'error' =>$file.' not found. The file might has been moved, renamed or deleted. Please reload the list and verify the file exists.');
            $result=$this->_upload($task_id, $file,$callback);
            if($result['result'] !==WPVIVID_SUCCESS)
            {
                $this->wpvivid_clear_backup_cache($task_id);
                return $result;
            }
        }
        $result=$this->upload_finish($task_id, $files);
        return $result;
        //return array('result' =>WPVIVID_SUCCESS);
    }

    public function _upload($task_id, $file,$callback)
    {
        $this -> current_file_size = filesize($file);
        $this -> current_file_name = basename($file);

        $upload_job=WPvivid_taskmanager::get_backup_sub_task_progress($task_id,'upload',WPVIVID_REMOTE_SEND_TO_SITE);

        global $wpvivid_plugin;
        $wpvivid_plugin->wpvivid_log->WriteLog('Start uploading '.basename($file),'notice');

        WPvivid_taskmanager::update_backup_sub_task_progress($task_id,'upload',WPVIVID_REMOTE_SEND_TO_SITE,WPVIVID_UPLOAD_UNDO,'Start uploading '.basename($file).'.',$upload_job['job_data']);

        $file_size=filesize($file);
        $md5=md5_file($file);
        $handle=fopen($file,'rb');

        $ret=$this->get_file_status($task_id,basename($file),$file_size,$md5);

        $wpvivid_plugin->wpvivid_log->WriteLog(wp_json_encode($ret),'notice');

        if($ret['result']==WPVIVID_SUCCESS)
        {
            if($ret['file_status']['status']=='finished')
            {
                $wpvivid_plugin->wpvivid_log->WriteLog('upload finished','notice');
                fclose($handle);
                $upload_job['job_data'][basename($file)]['uploaded']=1;
                WPvivid_taskmanager::update_backup_sub_task_progress($task_id,'upload',WPVIVID_REMOTE_SEND_TO_SITE,WPVIVID_UPLOAD_SUCCESS,'Uploading '.basename($file).' completed.',$upload_job['job_data']);
                return array('result' =>WPVIVID_SUCCESS);
            }
            else if($ret['file_status']['status']=='continue')
            {
                $offset=$ret['file_status']['offset'];
            }
            else
            {
                $offset=0;
            }
        }
        else
        {
            return $ret;
        }

        $retry_count=0;
        while (!feof($handle))
        {
            $general_setting=WPvivid_Setting::get_setting(true, "");
            if(!isset($general_setting['options']['wpvivid_common_setting']['migrate_size']) || empty($general_setting['options']['wpvivid_common_setting']['migrate_size'])){
                $general_setting['options']['wpvivid_common_setting']['migrate_size']=WPVIVID_SEND_TO_SITE_UPLOAD_SIZE;
            }
            $upload_size = $general_setting['options']['wpvivid_common_setting']['migrate_size'];
            $upload_size = intval($upload_size) * 1024;

            $ret=$this->send_chunk($task_id,$handle,basename($file),$offset,$upload_size,$file_size,$md5);
            if($ret['result']==WPVIVID_SUCCESS)
            {
                $status = WPvivid_taskmanager::get_backup_task_status($task_id);
                $status['resume_count']=0;
                WPvivid_taskmanager::update_backup_task_status($task_id, false, 'running', false, $status['resume_count']);
                if((time() - $this -> last_time) >3)
                {
                    if(is_callable($callback))
                    {
                        call_user_func_array($callback,array($offset,$this -> current_file_name,
                            $this->current_file_size,$this -> last_time,$this -> last_size));
                    }
                    $this -> last_size = $offset;
                    $this -> last_time = time();
                }

                if($ret['op']=='continue')
                {
                    continue;
                }
                else
                {
                    break;
                }
            }
            else
            {
                if($retry_count>3)
                {
                    if(isset($ret['http_code']))
                    {
                        if($ret['http_code']==413)
                        {
                            $ret['error']='Site migration failed. The receiving site can\'t receive the oversized data chunk. Please set the value of Chunk size to 512 KB in plugin settings. Then try again.';
                        }
                    }

                    return $ret;
                }
                else
                {
                    if(isset($ret['http_code']))
                    {
                        if($ret['http_code']==413)
                        {
                            $wpvivid_plugin->wpvivid_log->WriteLog('Site migration failed. The receiving site can\'t receive the oversized data chunk. Please set the value of Chunk size to 512 KB in plugin settings. Then try again. Chunk size: '.size_format($offset),'warning');
                        }
                        else
                        {
                            $wpvivid_plugin->wpvivid_log->WriteLog('upload file error offset:'.size_format($offset).' http error:'.$ret['http_code'],'warning');
                        }
                    }
                    else
                    {
                        $wpvivid_plugin->wpvivid_log->WriteLog('upload file error offset:'.size_format($offset).' error:'.$ret['error'],'warning');
                    }
                    $retry_count++;
                }
            }
        }
        $wpvivid_plugin->wpvivid_log->WriteLog('upload finished','notice');
        fclose($handle);
        $upload_job['job_data'][basename($file)]['uploaded']=1;
        WPvivid_taskmanager::update_backup_sub_task_progress($task_id,'upload',WPVIVID_REMOTE_SEND_TO_SITE,WPVIVID_UPLOAD_SUCCESS,'Uploading '.basename($file).' completed.',$upload_job['job_data']);
        return array('result' =>WPVIVID_SUCCESS);
    }

    public function wpvivid_clear_backup_cache($task_id)
    {
        global $wpvivid_plugin;
        $json=array();

        $json['backup_id']=$task_id;
        $json=wp_json_encode($json);
        $crypt=new WPvivid_crypt(base64_decode($this->options['token']));
        $data=$crypt->encrypt_message($json);

        $data=base64_encode($data);

        $wpvivid_plugin->wpvivid_log->WriteLog('Failed upload backup, clear backup cache.','notice');

        global $wp_version;
        $args['user-agent'] ='WordPress/' . $wp_version . '; ' . get_bloginfo('url');
        $args['body'] = $this->get_authenticated_request_body('clear_backup_cache', $data);
        if ($args['body'] === false)
        {
            return array(
                'result' => WPVIVID_FAILED,
                'error'  => 'The migration authentication key is invalid.',
            );
        }

        $args['timeout']=30;
        $response=wp_remote_post($this->options['url'],$args);

        if ( is_wp_error( $response ) )
        {
            $ret['result']=WPVIVID_FAILED;
            $ret['error']= $response->get_error_message();
            $wpvivid_plugin->wpvivid_log->WriteLog( $ret['error'],'notice');
        }
        else
        {
            if($response['response']['code']==200)
            {
                $res=json_decode($response['body'],1);
                if($res!=null)
                {
                    if($res['result']==WPVIVID_SUCCESS)
                    {
                        $ret['result']=WPVIVID_SUCCESS;
                    }
                    else
                    {
                        $ret['result']=WPVIVID_FAILED;
                        $ret['error']= $res['error'];
                        $wpvivid_plugin->wpvivid_log->WriteLog( $ret['error'],'notice');
                    }
                }
                else
                {
                    $ret['result']=WPVIVID_FAILED;
                    $ret['error']= 'Failed to parse returned data, unable to clear target site backup cache.';
                    $wpvivid_plugin->wpvivid_log->WriteLog( $ret['error'],'notice');
                }
            }
            else
            {
                $ret['result']=WPVIVID_FAILED;
                $ret['error']= 'Clear backup cache error '.$response['response']['code'].' '.$response['body'];
                $wpvivid_plugin->wpvivid_log->WriteLog( $ret['error'],'notice');
            }
        }
        return $ret;
    }

    public function connect_site($task_id)
    {
        $json=array();

        $json['backup_id']=$task_id;
        $json=wp_json_encode($json);
        $crypt=new WPvivid_crypt(base64_decode($this->options['token']));
        $data=$crypt->encrypt_message($json);

        $data=base64_encode($data);
        global $wp_version;
        $args['user-agent'] ='WordPress/' . $wp_version . '; ' . get_bloginfo('url');
        $args['body'] = $this->get_authenticated_request_body('send_to_site_connect', $data);
        if ($args['body'] === false)
        {
            return array(
                'result' => WPVIVID_FAILED,
                'error'  => 'The migration authentication key is invalid.',
            );
        }

        $args['timeout']=30;
        $response=wp_remote_post($this->options['url'],$args);

        if ( is_wp_error( $response ) )
        {
            $ret['result']=WPVIVID_FAILED;
            $ret['error']= $response->get_error_message();
        }
        else
        {
            if($response['response']['code']==200)
            {
                global $wpvivid_plugin;

                $res=json_decode($response['body'],1);
                if($res!=null)
                {
                    if($res['result']==WPVIVID_SUCCESS)
                    {
                        $ret['result']=WPVIVID_SUCCESS;
                    }
                    else
                    {
                        $ret['result']=WPVIVID_FAILED;
                        $ret['error']= $res['error'];
                    }
                }
                else
                {
                    $ret['result']=WPVIVID_FAILED;
                    $ret['error']= 'Failed to parse returned data, unable to establish connection with the target site.';
                }
            }
            else
            {
                $ret['result']=WPVIVID_FAILED;
                $ret['error']= 'Upload error '.$response['response']['code'].' '.$response['body'];
            }
        }

        return $ret;
    }

    public function send_chunk($task_id,$file_handle,$file_name,&$offset,$size,$file_size,$md5)
    {
        $upload_size=min($size,$file_size-$offset);

        if ($offset)
            fseek($file_handle, $offset);

        $data=fread($file_handle,$upload_size);

        if($data===false)
        {
            $ret['result']=WPVIVID_FAILED;
            $ret['error']='Read file error at:'.$offset;
            return $ret;
        }

        $json['backup_id']=$task_id;
        $json['name']=$file_name;
        $json['offset']=$offset;
        $json['size']=$upload_size;
        $json['file_size']=$file_size;
        $json['md5']=$md5;
        $json['data']=base64_encode($data);
        $json=wp_json_encode($json);

        $crypt=new WPvivid_crypt(base64_decode($this->options['token']));
        $data=$crypt->encrypt_message($json);

        $data=base64_encode($data);

        global $wp_version;
        $args['user-agent'] ='WordPress/' . $wp_version . '; ' . get_bloginfo('url');
        $args['body'] = $this->get_authenticated_request_body('send_to_site', $data);
        if ($args['body'] === false)
        {
            return array(
                'result' => WPVIVID_FAILED,
                'error'  => 'The migration authentication key is invalid.',
            );
        }

        $args['timeout']=30;

        global $wpvivid_plugin;
        $wpvivid_plugin->wpvivid_log->WriteLog('send chunk '.basename($file_name).' offset '.$offset,'notice');

        $response=wp_remote_post($this->options['url'],$args);

        $wpvivid_plugin->wpvivid_log->WriteLog('finished send chunk','notice');

        if ( is_wp_error( $response ) )
        {
            $ret['result']=WPVIVID_FAILED;
            $ret['error']= $response->get_error_message();
        }
        else
        {
            if($response['response']['code']==200)
            {
                $res=json_decode($response['body'],1);
                if($res!=null)
                {
                    if($res['result']==WPVIVID_SUCCESS)
                    {
                        $offset=$offset+$upload_size;
                        $ret['result']=WPVIVID_SUCCESS;
                        $ret['op']=$res['op'];
                    }
                    else
                    {
                        $ret['result']=WPVIVID_FAILED;
                        $ret['error']= $res['error'];
                        $wpvivid_plugin->wpvivid_log->WriteLog( $ret['error'],'notice');
                    }

                }
                else
                {
                    $ret['result']=WPVIVID_FAILED;
                    $ret['error']= 'Failed to parse returned data, chunk transfer failed.';
                    $wpvivid_plugin->wpvivid_log->WriteLog('error send chunk failed','notice');
                }
            }
            else
            {
                $ret['result']=WPVIVID_FAILED;
                $ret['http_code']=$response['response']['code'];
                $ret['error']= 'http error, error code:'.$response['response']['code'];
            }
        }
        return $ret;
    }

    public function upload_finish($task_id, $files)
    {
        if (!$this->is_valid_backup_id($task_id) || !is_array($files) || empty($files))
        {
            return array(
                'result'=>WPVIVID_FAILED,
                'error'=>'Invalid backup file manifest.',
            );
        }

        $manifest=array();
        foreach ($files as $file)
        {
            if (!is_string($file) || !is_file($file))
            {
                return array(
                    'result'=>WPVIVID_FAILED,
                    'error'=>'Failed to build the backup file manifest.',
                );
            }

            $file_size=filesize($file);
            $file_md5=md5_file($file);
            if ($file_size===false || $file_md5===false)
            {
                return array(
                    'result'=>WPVIVID_FAILED,
                    'error'=>'Failed to read a backup file.',
                );
            }

            $manifest[]=array(
                'file_name'=>basename($file),
                'size'=>$file_size,
                'md5'=>strtolower($file_md5),
            );
        }

        $json=array();
        $json['backup_id']=$task_id;
        $json['files']=$manifest;
        $json=wp_json_encode($json);

        $crypt=new WPvivid_crypt(base64_decode($this->options['token']));
        $data=$crypt->encrypt_message($json);

        $data=base64_encode($data);
        global $wp_version;
        $args['user-agent'] ='WordPress/' . $wp_version . '; ' . get_bloginfo('url');
        $args['body'] = $this->get_authenticated_request_body('send_to_site_finish', $data);
        if ($args['body'] === false)
        {
            return array(
                'result' => WPVIVID_FAILED,
                'error'  => 'The migration authentication key is invalid.',
            );
        }

        $args['timeout']=30;
        $response=wp_remote_post($this->options['url'],$args);
        if ( is_wp_error( $response ) )
        {
            $ret['result']=WPVIVID_FAILED;
            $ret['error']= $response->get_error_message();
        }
        else
        {
            if($response['response']['code']==200)
            {
                $res=json_decode($response['body'],1);
                if($res!=null)
                {
                    if($res['result']==WPVIVID_SUCCESS)
                    {
                        $ret['result']=WPVIVID_SUCCESS;
                    }
                    else
                    {
                        $ret['result']=WPVIVID_FAILED;
                        $ret['error']= $res['error'];
                    }
                }
                else
                {
                    $ret['result']=WPVIVID_FAILED;
                    $ret['error']= 'Failed to parse returned data, uploading backup chunks failed.';
                }
            }
            else
            {
                $ret['result']=WPVIVID_FAILED;
                $ret['error']= 'Upload error '.$response['response']['code'];
            }
        }

        return $ret;
    }

    public function send_to_site_connect()
    {
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-crypt.php';
        try {
            if (isset($_POST['wpvivid_content'])) {
                $default = array();
                $option = get_option('wpvivid_api_token', $default);
                if (empty($option)) {
                    die();
                }
                if ($option['expires'] != 0 && $option['expires'] < time()) {
                    die();
                }

                $private_key = base64_decode($option['private_key'], true);

                if ($private_key === false)
                {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'Invalid migration private key.';
                    echo wp_json_encode($ret);
                    die();
                }

                $content = wp_unslash($_POST['wpvivid_content']);
                $body = base64_decode($content, true);

                if ($body === false)
                {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'Invalid encrypted data.';
                    echo wp_json_encode($ret);
                    die();
                }

                $crypt = new WPvivid_crypt($private_key);
                $data = $crypt->decrypt_message($body);
                if (!is_string($data))
                {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'Data decryption failed.';
                    echo wp_json_encode($ret);
                    die();
                }

                $params = json_decode($data, 1);
                if (is_null($params))
                {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'Data decode failed.';
                    echo wp_json_encode($ret);
                    die();
                }

                if (!is_array($params))
                {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'Invalid request data.';
                    echo wp_json_encode($ret);
                    die();
                }

                if (isset($params['backup_id']))
                {
                    if (!$this->is_valid_backup_id($params['backup_id']))
                    {
                        $ret['result'] = WPVIVID_FAILED;
                        $ret['error'] = 'Invalid backup ID.';
                        echo wp_json_encode($ret);
                        die();
                    }

                    $backup_id = $params['backup_id'];

                    if (WPvivid_Backuplist::get_backup_by_id($backup_id) !== false)
                    {
                        $ret['result'] = WPVIVID_FAILED;
                        $ret['error'] = 'The uploading backup already exists in Backups list.';
                        echo wp_json_encode($ret);
                        die();
                    }
                    else {
                        global $wpvivid_plugin;
                        $wpvivid_plugin->wpvivid_log = new WPvivid_Log();

                        $log_file = $wpvivid_plugin->wpvivid_log->GetSaveLogFolder() . $backup_id . '_backup_log.txt';

                        if (!file_exists($log_file))
                        {
                            $wpvivid_plugin->wpvivid_log->CreateLogFile($backup_id . '_backup', 'no_folder', 'transfer');
                            $wpvivid_plugin->wpvivid_log->WriteLogHander();
                        }
                        else
                        {
                            $wpvivid_plugin->wpvivid_log->OpenLogFile($backup_id . '_backup', 'no_folder');
                        }

                        $wpvivid_plugin->wpvivid_log->WriteLog('Connect site success', 'notice');
                        $ret['result'] = WPVIVID_SUCCESS;
                        echo wp_json_encode($ret);
                        die();
                    }
                }

                if (isset($params['test_connect']) && (int)$params['test_connect'] === 1)
                {
                    $ret['result'] = WPVIVID_SUCCESS;
                    echo wp_json_encode($ret);
                    die();
                }

                /*
                 * Neither a backup connection nor a connection test.
                 */
                $ret['result'] = WPVIVID_FAILED;
                $ret['error'] = 'Invalid migration connection request.';
                echo wp_json_encode($ret);
                die();
            }
        }
        catch (Exception $e) {
            $ret['result']=WPVIVID_FAILED;
            $ret['error']=$e->getMessage();
            echo wp_json_encode($ret);
            die();
        }
        die();
    }

    public function send_to_site()
    {
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-crypt.php';
        $test_log=new WPvivid_Log();
        $test_log->CreateLogFile('test_backup','no_folder','transfer');
        $test_log->WriteLog('test upload.','notice');
        try
        {
            if(isset($_POST['wpvivid_content']))
            {
                global $wpvivid_plugin;
                $wpvivid_plugin->wpvivid_log=new WPvivid_Log();

                $default=array();
                $option=get_option('wpvivid_api_token',$default);
                if(empty($option))
                {
                    die();
                }
                if($option['expires'] !=0 && $option['expires']<time())
                {
                    die();
                }
                $crypt=new WPvivid_crypt(base64_decode($option['private_key']));
                $body=base64_decode($_POST['wpvivid_content']);
                $data=$crypt->decrypt_message($body);
                if (!is_string($data))
                {
                    $ret['result']=WPVIVID_FAILED;
                    $ret['error']='The key is invalid.';
                    echo wp_json_encode($ret);
                    die();
                }

                $params=json_decode($data,1);
                if(is_null($params))
                {
                    $ret['result']=WPVIVID_FAILED;
                    $ret['error']='The key is invalid.';
                    echo wp_json_encode($ret);
                    die();
                }

                if (!isset($params['backup_id']) || !$this->is_valid_backup_id($params['backup_id']))
                {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'Invalid backup ID.';
                    echo wp_json_encode($ret);
                    die();
                }

                $backup_id = $params['backup_id'];

                $wpvivid_plugin->wpvivid_log->OpenLogFile($backup_id.'_backup','no_folder','backup');
                $wpvivid_plugin->wpvivid_log->WriteLog('start upload.','notice');
                $dir=WPvivid_Setting::get_backupdir();

                $safe_name = basename($params['name']);
                $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '', $safe_name);
                $allowed_extensions = array('zip', 'gz', 'tar', 'sql');
                $file_ext = strtolower(pathinfo($safe_name, PATHINFO_EXTENSION));
                if (!in_array($file_ext, $allowed_extensions, true))
                {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'Invalid file type - only backup files allowed.';
                    echo wp_json_encode($ret);
                    die();
                }
                $file_path=WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.str_replace('wpvivid', 'wpvivid_temp', $safe_name);
                if(!file_exists($file_path))
                {
                    $handle=fopen($file_path,'w');
                    fclose($handle);
                }

                $handle=fopen($file_path,'rb+');
                $offset=$params['offset'];
                $wpvivid_plugin->wpvivid_log->WriteLog('Write file:'.$file_path.' offset:'.size_format($offset),'notice');
                if($offset)
                {
                    if(fseek($handle, $offset)===-1)
                    {
                        $wpvivid_plugin->wpvivid_log->WriteLog('Seek file offset failed:'.size_format($offset),'notice');
                    }
                }

                if (fwrite($handle,base64_decode($params['data'])) === FALSE)
                {
                    $wpvivid_plugin->wpvivid_log->WriteLog('Write file :'.$file_path.' failed size:'.filesize($file_path),'notice');
                }
                else
                {
                    $wpvivid_plugin->wpvivid_log->WriteLog('Write file:'.$file_path.' success size:'.filesize($file_path),'notice');
                }

                fclose($handle);


                if(filesize($file_path)>=$params['file_size'])
                {
                    if (md5_file($file_path) == $params['md5'])
                    {
                        $wpvivid_plugin->wpvivid_log->WriteLog('rename temp file:'.$file_path.' to new name:'.WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.$params['name'],'notice');
                        rename($file_path,WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.$safe_name);
                        $ret['result']=WPVIVID_SUCCESS;
                        $ret['op']='finished';
                    } else {
                        $wpvivid_plugin->wpvivid_log->WriteLog('file md5 not match','notice');
                        $ret['result']=WPVIVID_FAILED;
                        $ret['error']='File md5 is not matched.';
                    }
                }
                else
                {
                    $wpvivid_plugin->wpvivid_log->WriteLog('continue size:'.filesize($file_path).' size1:'.$params['file_size'],'notice');
                    $ret['result']=WPVIVID_SUCCESS;
                    $ret['op']='continue';
                }

                echo wp_json_encode($ret);
            }
        }
        catch (Exception $e)
        {
            $ret['result']=WPVIVID_FAILED;
            $ret['error']=$e->getMessage();
            echo wp_json_encode($ret);
            die();
        }

        die();
    }

    public function send_to_site_finish()
    {
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-crypt.php';
        try {
            if (isset($_POST['wpvivid_content'])) {
                $default = array();
                $option = get_option('wpvivid_api_token', $default);
                if (empty($option)) {
                    die();
                }
                if ($option['expires'] != 0 && $option['expires'] < time()) {
                    die();
                }
                $crypt = new WPvivid_crypt(base64_decode($option['private_key']));
                $body = base64_decode($_POST['wpvivid_content']);
                $data = $crypt->decrypt_message($body);
                if (!is_string($data)) {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'The key is invalid.';
                    echo wp_json_encode($ret);
                    die();
                }
                $params = json_decode($data, 1);
                if (is_null($params)) {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'The key is invalid.';
                    echo wp_json_encode($ret);
                    die();
                }

                if (!isset($params['backup_id']) || !$this->is_valid_backup_id($params['backup_id']))
                {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'Invalid backup ID.';
                    echo wp_json_encode($ret);
                    die();
                }

                if (!isset($params['files']) || !is_array($params['files']) || empty($params['files']))
                {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'Invalid backup file manifest.';
                    echo wp_json_encode($ret);
                    die();
                }

                $backup_id = $params['backup_id'];

                global $wpvivid_plugin;
                $wpvivid_plugin->wpvivid_log = new WPvivid_Log();
                $wpvivid_plugin->wpvivid_log->OpenLogFile($backup_id . '_backup', 'no_folder', 'backup');
                $wpvivid_plugin->wpvivid_log->WriteLog('upload finished', 'notice');

                if (!class_exists('WPvivid_Backup_Registration'))
                {
                    include_once WPVIVID_PLUGIN_DIR.'/includes/backup-registration/class-wpvivid-backup-registration.php';
                }

                $registration=new WPvivid_Backup_Registration();
                $ret=$registration->register_backup(
                    $backup_id,
                    $params['files'],
                    array(
                        'type'        =>'Migration',
                        'log'         =>$wpvivid_plugin->wpvivid_log->log_file,
                        'verify_md5'  => true,
                    )
                );

                echo wp_json_encode($ret);
            }
        }
        catch (Exception $e) {
            $ret['result']=WPVIVID_FAILED;
            $ret['error']=$e->getMessage();
            echo wp_json_encode($ret);
            die();
        }
        die();
    }

    /**
     * Builds backup-list data from the legacy migration task structure.
     *
     * The current migration process sends a backup ID and file manifest and
     * registers the received backup through WPvivid_Backup_Registration.
     *
     * @deprecated 0.9.135 No longer used by the current migration process.
     *
     * @param array $task Legacy backup task data.
     * @return array
     */
    public function get_backup_data_by_task($task)
    {
        global $wpvivid_plugin;
        $backup_data=array();
        $backup_data['type']='Migration';
        $backup_data['create_time']=$task['status']['start_time'];
        $backup_data['manual_delete']=0;
        $backup_data['local']['path']=WPvivid_Setting::get_backupdir();
        $backup_data['compress']['compress_type']=$task['options']['backup_options']['compress']['compress_type'];
        $backup_data['save_local']=$task['options']['save_local'];
        $backup_data['log']=$wpvivid_plugin->wpvivid_log->log_file;
        $backup_data['backup']=$this->get_backup_result_by_task($task);
        $backup_data['remote']=array();
        $backup_data['lock']=0;
        $backup_data=apply_filters('wpvivid_get_backup_data_by_task',$backup_data,$task);
        return $backup_data;
    }

    /**
     * Builds the backup result from the legacy migration task structure.
     *
     * This method was used by get_backup_data_by_task() when the complete backup
     * task was sent to the target site.
     *
     * @deprecated 0.9.135 No longer used by the current migration process.
     *
     * @param array $task Legacy backup task data.
     * @return array
     */
    public function get_backup_result_by_task($task)
    {
        $ret['result']=WPVIVID_SUCCESS;
        $ret['files']=array();
        foreach ($task['options']['backup_options']['backup'] as $backup_data)
        {
            if($task['options']['backup_options']['ismerge']==1)
            {
                if (!defined('WPVIVID_BACKUP_TYPE_MERGE'))
                    define('WPVIVID_BACKUP_TYPE_MERGE','backup_merge');
                if(WPVIVID_BACKUP_TYPE_MERGE==$backup_data['key'])
                {
                    $ret=$backup_data['result'];
                    if($ret['result']!==WPVIVID_SUCCESS)
                    {
                        return $ret;
                    }
                }
            }
            else
            {
                $ret['files']=array_merge($ret['files'],$backup_data['result']['files']);
            }
        }
        return $ret;
    }

    public function cleanup($files)
    {
        return array('result' => WPVIVID_SUCCESS);
    }

    public function download($file, $local_path, $callback = '')
    {
        return array('result' => WPVIVID_SUCCESS);
    }

    public function get_file_status($task_id,$file,$file_size,$md5)
    {
        $json=array();

        $json['backup_id']=$task_id;
        $json['name']=$file;
        $json['file_size']=$file_size;
        $json['md5']=$md5;
        $json=wp_json_encode($json);
        $crypt=new WPvivid_crypt(base64_decode($this->options['token']));
        $data=$crypt->encrypt_message($json);
        $data=base64_encode($data);
        global $wp_version;
        $args['user-agent'] ='WordPress/' . $wp_version . '; ' . get_bloginfo('url');
        $args['body'] = $this->get_authenticated_request_body('send_to_site_file_status', $data);
        if ($args['body'] === false)
        {
            return array(
                'result' => WPVIVID_FAILED,
                'error'  => 'The migration authentication key is invalid.',
            );
        }

        $args['timeout']=30;
        $response=wp_remote_post($this->options['url'],$args);
        if ( is_wp_error( $response ) )
        {
            $ret['result']=WPVIVID_FAILED;
            $ret['error']= $response->get_error_message();
        }
        else
        {
            if($response['response']['code']==200)
            {
                global $wpvivid_plugin;

                $res=json_decode($response['body'],1);
                if($res!=null)
                {
                    if($res['result']==WPVIVID_SUCCESS)
                    {
                        $ret['result']=WPVIVID_SUCCESS;
                        $ret['file_status']=$res['file_status'];
                    }
                    else
                    {
                        $ret['result']=WPVIVID_FAILED;
                        $ret['error']= $res['error'];
                    }
                }
                else
                {
                    $ret['result']=WPVIVID_FAILED;
                    $ret['error']= 'Failed to parse returned data, unable to retrieve file status of target site.';
                }
            }
            else
            {
                $ret['result']=WPVIVID_FAILED;
                $ret['error']= 'Upload error '.$response['response']['code'].' '.$response['body'];
            }
        }
        return $ret;
    }

    public function send_to_site_file_status()
    {
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-crypt.php';
        try {
            if (isset($_POST['wpvivid_content'])) {
                $default = array();
                $option = get_option('wpvivid_api_token', $default);
                if (empty($option)) {
                    die();
                }
                if ($option['expires'] != 0 && $option['expires'] < time()) {
                    die();
                }

                $crypt = new WPvivid_crypt(base64_decode($option['private_key']));
                $body = base64_decode($_POST['wpvivid_content']);
                $data = $crypt->decrypt_message($body);
                if (!is_string($data)) {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'The key is invalid.';
                    echo wp_json_encode($ret);
                    die();
                }

                $params = json_decode($data, 1);
                if (is_null($params)) {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'The key is invalid.';
                    echo wp_json_encode($ret);
                    die();
                }

                if (!isset($params['backup_id']) || !$this->is_valid_backup_id($params['backup_id']))
                {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'Invalid backup ID.';
                    echo wp_json_encode($ret);
                    die();
                }

                $dir = WPvivid_Setting::get_backupdir();
                $safe_name = basename($params['name']);
                $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '', $safe_name);
                $allowed_extensions = array('zip', 'gz', 'tar', 'sql');
                $file_ext = strtolower(pathinfo($safe_name, PATHINFO_EXTENSION));
                if (!in_array($file_ext, $allowed_extensions, true))
                {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'Invalid file type - only backup files allowed.';
                    echo wp_json_encode($ret);
                    die();
                }
                $file_path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . str_replace('wpvivid', 'wpvivid_temp', $safe_name);
                $rename = true;

                if (!file_exists($file_path))
                {
                    $file_path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $safe_name;
                    $rename = false;
                    $offset=false;
                }
                else
                {
                    $offset = filesize($file_path);
                }

                if (!$offset) {
                    $ret['result'] = WPVIVID_SUCCESS;
                    $ret['file_status']['status'] = 'start';
                    echo wp_json_encode($ret);
                    die();
                }

                if (filesize($file_path) >= $params['file_size']) {
                    if (md5_file($file_path) == $params['md5']) {
                        if ($rename)
                            rename($file_path, WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $safe_name);
                        $ret['result'] = WPVIVID_SUCCESS;
                        $ret['file_status']['status'] = 'finished';
                    } else {
                        $ret['result'] = WPVIVID_FAILED;
                        $ret['error'] = 'File md5 is not matched.';
                    }
                } else {
                    $ret['result'] = WPVIVID_SUCCESS;
                    $ret['file_status']['status'] = 'continue';
                    $ret['file_status']['offset'] = filesize($file_path);
                }
                echo wp_json_encode($ret);
            }
        }
        catch (Exception $e) {
            $ret['result']=WPVIVID_FAILED;
            $ret['error']=$e->getMessage();
            echo wp_json_encode($ret);
            die();
        }
        die();
    }

    public function clear_backup_cache()
    {
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-crypt.php';
        try {
            if (isset($_POST['wpvivid_content'])) {
                $default = array();
                $option = get_option('wpvivid_api_token', $default);
                if (empty($option)) {
                    die();
                }
                if ($option['expires'] != 0 && $option['expires'] < time()) {
                    die();
                }

                $crypt = new WPvivid_crypt(base64_decode($option['private_key']));
                $body = base64_decode($_POST['wpvivid_content']);
                $data = $crypt->decrypt_message($body);

                if (!is_string($data)) {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'The key is invalid.';
                    echo wp_json_encode($ret);
                    die();
                }
                $params = json_decode($data, 1);
                if (is_null($params)) {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'The key is invalid.';
                    echo wp_json_encode($ret);
                    die();
                }

                if (!isset($params['backup_id']) || !$this->is_valid_backup_id($params['backup_id']))
                {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'Invalid backup ID.';
                    echo wp_json_encode($ret);
                    die();
                }

                $backup_id = $params['backup_id'];

                global $wpvivid_plugin;
                $wpvivid_plugin->wpvivid_log = new WPvivid_Log();
                $wpvivid_plugin->wpvivid_log->OpenLogFile($backup_id . '_backup', 'no_folder', 'backup');

                $dir=WPvivid_Setting::get_backupdir();


                $backup_path=WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR;
                if(is_dir($backup_path))
                {
                    $handler = opendir($backup_path);
                    if($handler!==false)
                    {
                        while (($filename = readdir($handler)) !== false)
                        {
                            if ($filename != "." && $filename != "..")
                            {
                                if (is_dir($backup_path  . $filename))
                                {
                                    continue;
                                }
                                else {
                                    if (self::is_wpvivid_backup($filename))
                                    {
                                        if ($id =self::get_wpvivid_backup_id($filename))
                                        {
                                            $white_label_id = str_replace(apply_filters('wpvivid_white_label_file_prefix', 'wpvivid'), 'wpvivid', $id);
                                            if($id === $backup_id || $white_label_id === $backup_id)
                                            {
                                                $wpvivid_plugin->wpvivid_log->WriteLog('Clear backup file: '.$backup_path.$filename, 'notice');
                                                @wp_delete_file($backup_path.$filename);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if($handler)
                            @closedir($handler);
                    }
                    $ret['result'] = WPVIVID_SUCCESS;
                }
                else{
                    $ret['result']='failed';
                    $ret['error']='Failed to get local storage directory.';
                }
                echo wp_json_encode($ret);
            }
        }
        catch (Exception $e) {
            $ret['result']=WPVIVID_FAILED;
            $ret['error']=$e->getMessage();
            echo wp_json_encode($ret);
            die();
        }
        die();
    }

    public static function is_wpvivid_backup($file_name)
    {
        if (preg_match('/wpvivid-.*_.*_.*\.zip$/', $file_name))
        {
            return true;
        }
        else {
            return false;
        }
    }

    public static function get_wpvivid_backup_id($file_name)
    {
        if (preg_match('/wpvivid-(.*?)_/', $file_name, $matches))
        {
            $id = $matches[0];
            $id = substr($id, 0, strlen($id) - 1);
            return $id;
        }
        else {
            return false;
        }
    }

    private function get_authenticated_request_body($action, $content)
    {
        if (!isset($this->options['auth_key'], $this->options['protocol_version']) ||
            !is_string($this->options['auth_key']) ||
            preg_match('/\A[a-f0-9]{64}\z/', $this->options['auth_key']) !== 1 ||
            (int)$this->options['protocol_version'] !== 2
        )
        {
            return false;
        }

        $signature = hash_hmac(
            'sha256',
            $action . "\n" . $content,
            $this->options['auth_key']
        );

        return array(
            'wpvivid_content'          => $content,
            'wpvivid_action'           => $action,
            'wpvivid_protocol_version' => 2,
            'wpvivid_client_type'      => 'free',
            'wpvivid_client_version'   => defined('WPVIVID_PLUGIN_VERSION') ? WPVIVID_PLUGIN_VERSION : '',
            'wpvivid_signature'        => $signature,
        );
    }

    private function check_migration_client_version()
    {
        if (!isset($_POST['wpvivid_client_type'], $_POST['wpvivid_client_version']) ||
            !is_string($_POST['wpvivid_client_type']) ||
            !is_string($_POST['wpvivid_client_version']))
        {
            return array(
                'result'     => WPVIVID_FAILED,
                'error_code' => 'migration_client_upgrade_required',
                'error'      => __('The source site is using an outdated version of WPvivid. Please update the WPvivid plugin on the source site, generate a new migration key, and try again.', 'wpvivid-backuprestore'),
            );
        }

        $client_type = sanitize_key(wp_unslash($_POST['wpvivid_client_type']));
        $client_version = sanitize_text_field(wp_unslash($_POST['wpvivid_client_version']));

        if ($client_type === 'free')
        {
            $minimum_version = '0.9.135';
            $plugin_name = 'WPvivid Backup & Migration';
        }
        else if ($client_type === 'pro')
        {
            $minimum_version = '2.2.52';
            $plugin_name = 'WPvivid Backup Pro';
        }
        else
        {
            return array(
                'result'     => WPVIVID_FAILED,
                'error_code' => 'invalid_migration_client',
                'error'      => __('Invalid migration client.', 'wpvivid-backuprestore'),
            );
        }

        if (!$this->is_supported_migration_client_version($client_version, $minimum_version))
        {
            return array(
                'result'          => WPVIVID_FAILED,
                'error_code'      => 'migration_client_upgrade_required',
                'client_type'     => $client_type,
                'client_version'  => $client_version,
                'minimum_version' => $minimum_version,
                'error'           => sprintf(
                    __('The source site is using %1$s version %2$s. Please update it to version %3$s or later, generate a new migration key, and try again.', 'wpvivid-backuprestore'),
                    $plugin_name,
                    $client_version !== '' ? $client_version : __('unknown', 'wpvivid-backuprestore'),
                    $minimum_version
                ),
            );
        }

        return array('result' => WPVIVID_SUCCESS);
    }

    private function is_supported_migration_client_version($client_version, $minimum_version)
    {
        if (!is_string($client_version) ||
            preg_match('/\A[0-9]+(?:\.[0-9]+){1,3}(?:[-+][a-zA-Z0-9.-]+)?\z/', $client_version) !== 1)
        {
            return false;
        }

        if (version_compare($client_version, $minimum_version, '>='))
        {
            return true;
        }

        /*
         * Allow pre-release builds from the first supported release line,
         * for example 0.9.135-beta2 and 2.2.52-beta1.
         */
        return strpos($client_version, $minimum_version . '-') === 0;
    }

    private function verify_request_auth($action)
    {
        if (!isset($_POST['wpvivid_content'], $_POST['wpvivid_signature'], $_POST['wpvivid_protocol_version']) ||
            !is_string($_POST['wpvivid_content']) ||
            !is_string($_POST['wpvivid_signature']))
        {
            return false;
        }

        if (absint($_POST['wpvivid_protocol_version']) !== 2)
        {
            return false;
        }

        $option = get_option('wpvivid_api_token', array());

        if (empty($option) || !isset($option['auth_key']) ||
            !is_string($option['auth_key']) ||
            preg_match('/\A[a-f0-9]{64}\z/', $option['auth_key']) !== 1)
        {
            return false;
        }

        if (isset($option['expires']) && (int)$option['expires'] !== 0 && (int)$option['expires'] < time())
        {
            return false;
        }

        $content = wp_unslash($_POST['wpvivid_content']);

        $signature = sanitize_text_field(wp_unslash($_POST['wpvivid_signature']));

        if (preg_match('/\A[a-f0-9]{64}\z/', $signature) !== 1)
        {
            return false;
        }

        $expected_signature = hash_hmac(
            'sha256',
            $action . "\n" . $content,
            $option['auth_key']
        );

        return hash_equals($expected_signature, $signature);
    }

    private function is_valid_backup_id($backup_id)
    {
        return is_string($backup_id) &&
            $backup_id !== '' &&
            $backup_id !== '0' &&
            preg_match(
                '/\A[a-zA-Z0-9_-]+\z/',
                $backup_id
            ) === 1;
    }
}
