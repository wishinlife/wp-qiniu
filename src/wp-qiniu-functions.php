<?php
require_once( dirname( __FILE__ ) . '/lib/autoload.php' );
use Qiniu\Auth;		// 引入鉴权类
use Qiniu\Storage\BucketManager;

if (!defined('WP_QINIU_FUNCTIONS_LOAD')) {
	define('WP_QINIU_FUNCTIONS_LOAD', 'WP_QINIU_LOADED');

	// 用一个函数来列出云存储中某个目录下的文件（夹）
	function wp_qiniu_storage_list_files($dir_qiniu_path, $limit, $marker = null){
		global $bucketMgr;

		// 要列取文件的公共前缀
		$prefix = $dir_qiniu_path;

		// 列举文件
		list($filelists, $marker, $err) = $bucketMgr->listFiles(WP_QINIU_STORAGE_BUCKET, $prefix, $marker, $limit, '/');
		if ($err !== null) {
			//echo "\n====> list file err: \n";
			//var_dump($err);
			die("list file err: ".$err->message());
		} else {
			//$filelists = json_decode($iterms);
			return array($marker, $filelists['items']);
		}
	}

	//获取原文件下载地址
	function wp_qiniu_get_download_url($key, $expires = 14400)
	{
		if(!$key)
			return '';

		//$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
		$retUrl = 'http://' . WP_QINIU_STORAGE_DOMAIN . '/' . $key;
		if (WP_QINIU_IMAGE_PROTECT) {
			global $auth;
			//baseUrl构造成私有空间的域名/key的形式，开启原图保护的原文件下载与私有空间相同
			$retUrl = $auth->privateDownloadUrl($retUrl, $expires);
		} 
		return esc_url($retUrl);
	}

	function wp_qiniu_upload($fileContent, $key)
	{
		global $auth, $uploadMgr;
		$token = $auth->uploadToken(WP_QINIU_BACKUP_BUCKET);// 生成上传 Token

		// 调用 UploadManager 的 putFile 方法进行文件的上传。
		$ret = $uploadMgr->put($token, $key, $fileContent);
		return $ret;
	}

	function wp_qiniu_uploadFile($filePath, $key)
	{
		global $auth, $uploadMgr;
		$token = $auth->uploadToken(WP_QINIU_BACKUP_BUCKET);// 生成上传 Token

		// 调用 UploadManager 的 putFile 方法进行文件的上传。
		$ret = $uploadMgr->putFile($token, $key, $filePath);
		return $ret;
	}

	//检查连接七牛存储是否正常
	function wp_qiniu_checklink()
	{
		//$accessKey = get_option('wp_qiniu_access_key');
		//$secretKey = get_option('wp_qiniu_secret_key');
		//global $bucketMgr;

		$auth = new Auth(get_option('wp_qiniu_access_key'), get_option('wp_qiniu_secret_key'));// 构建鉴权对象
		$bucketMgr = new BucketManager($auth);

		if (!get_option('wp_qiniu_access_key') || !get_option('wp_qiniu_secret_key') || !get_option('wp_qiniu_storage_bucket') || !get_option('wp_qiniu_backup_bucket'))
			return array('type' => 'error', 'message' => '不能连接七牛云存储，请按要求设置Access Key、Secret Key、数据存储空间名和备份存储空间名等设置。');

		$type = 'error';
		$msg = '';
		try {
			list($buckets, $err) = $bucketMgr->buckets();
			if ($err) {
				$msg .= '七牛云存储连接异常！错误信息：' . $err->message();
			} else {
				$isStorageBucket = in_array(get_option('wp_qiniu_storage_bucket'), $buckets);
				$isBackupBucket = in_array(get_option('wp_qiniu_backup_bucket'), $buckets);
				if ($isStorageBucket && $isBackupBucket) {
					$type = 'success';
					$msg .= '七牛云存储连接正常，存储空间设置正常。';
				}
				if (!$isStorageBucket)
					$msg .= '七牛云存储连接正常，但数据存储空间' . get_option('wp_qiniu_storage_bucket') . '不存在！';
				if (!$isBackupBucket)
					$msg .= '七牛云存储连接正常，但备份存储空间' . get_option('wp_qiniu_backup_bucket') . '不存在！';
			}
			if(get_option('wp_qiniu_feed_actived') && !get_option('wp_qiniu_actived') && $type == 'success'){
				$result = get_by_curl('http://www.syncy.cn/wpqiniu','action=active&email='.get_option('admin_email').'&domain='.WP_QINIU_SITE_DOMAIN);
				if($result == 'success')
					update_option('wp_qiniu_actived', true);
			}
		} catch (Exception $ex) {
			$msg = '七牛云存储连接异常！错误信息：' . $ex->getMessage();
		}

		return array('type' => $type, 'message' => $msg);
	}
	//根据 pid 获取文件夹的路径，以‘/’结尾
	function wp_qiniu_get_prefix_by_pid($pid){
		global $wpdb;
		$table_name = $wpdb->prefix.'wp_qiniu_files';
		$path = '';
		while ((int)$pid !== 0){
			$row = $wpdb->get_row($wpdb->prepare("SELECT pid,fname FROM $table_name WHERE id=%d and isdir=1",$pid), ARRAY_A);
			if($row){
				$path = $row['fname'].'/'.$path;
				$pid = $row['pid'];
			}else {
				if($row === false)
					return false;
				else
					return null;
			}
		}
		return $path;
	}
	//根据文件 id 获取文件的 key
	function wp_qiniu_get_key_by_id($id){
		if($id < 1)
			return false;
		global $wpdb;
		$table_name = $wpdb->prefix.'wp_qiniu_files';
		$row = $wpdb->get_row($wpdb->prepare("SELECT pid,fname,isdir FROM $table_name WHERE id=%d",$id), ARRAY_A);
		if($row){
			$fkey = $row['fname'];
			$id = $row['pid'];
			$isdir = $row['isdir'];
		}else {
			return false;
		}
		while ((int)$id !== 0){
			$row = $wpdb->get_row($wpdb->prepare("SELECT pid,fname FROM $table_name WHERE id=%d and isdir=1",$id), ARRAY_A);
			if($row){
				$fkey = $row['fname'].'/'.$fkey;
				$id = $row['pid'];
			}else {
				return false;
			}
		}
		return array($isdir, $fkey);
	}
	//删除文件及文件夹
	function wp_qiniu_delete_file($table_name, $id, $key, $isdir)
	{
		global $wpdb, $bucketMgr;
		if($isdir) {
			$ok = true;
			$childs = $wpdb->get_results("SELECT * FROM `$table_name` where pid=$id;", ARRAY_A);
			if ($childs){
				foreach($childs as $childFile){
					$ok &= wp_qiniu_delete_file($table_name, $childFile['id'], $key.'/'.$childFile['fname'], $childFile['isdir']);
				}
			}
			if($ok)
				return $wpdb->delete($table_name, array('id' => $id), array('%d'));    //删除文件夹
			else
				return false;
		}else{
			$err = $bucketMgr->delete(WP_QINIU_STORAGE_BUCKET, $key);	// 删除七牛文件  $key
			if($err !== null && $err->code() != 612)  //612 待删除资源不存在
				return false;
			else
				return $wpdb->delete($table_name, array('id' => $id), array('%d'));
		}
	}
	// 重命名文件
	function wp_qiniu_file_rename($id, $newname)
	{
		global $wpdb, $bucketMgr;
		$table_name = $wpdb->prefix.'wp_qiniu_files';
		$row = $wpdb->get_row($wpdb->prepare("SELECT pid,fname FROM $table_name WHERE id=%d and isdir=0",$id), ARRAY_A);
		if(!$row)
			return array('status' => 'false', 'error' => '获取文件信息错误！'.(empty($row)?'文件不存在。':$wpdb->last_error));
		$pid = $row['pid'];
		$oldname = $row['fname'];
		$check = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE pid=%d AND id<>%d AND fname=%s and isdir=0",$pid, $id,$newname));
		if($check){
			return array('status' => 'false', 'error' => '已存在相同名称的文件！');
		}else if($check === false){
			$err = $wpdb->last_error;
			return array('status' => 'false', 'error' => $err);
		}else {
			$ppath = wp_qiniu_get_prefix_by_pid($pid);
			if($ppath === false || $ppath === null)
				return array('status' => 'false', 'error' => '获取文件路径错误！'.(($ppath === false)?$wpdb->last_error:''));
			$oldkey = $ppath.$oldname;
			$newkey = $ppath.$newname;
			$err = $bucketMgr->rename(WP_QINIU_STORAGE_BUCKET, $oldkey, $newkey);
			if($err !== null && $err->code() != 612)
				return array('status' => 'false', 'error' => '七牛云存储文件重命名失败：'.$err->message());
			else {
				$ok = $wpdb->update( $table_name, array( 'fname' => $newname ), array( 'id' => $id ), array( '%s' ), array( '%u' ) );
				if ( ! $ok ) {
					$err = $wpdb->last_error;
					return array( 'status' => 'false', 'error' => $err );
				} else {
					return array( 'status' => 'success' );
				}
			}
		}
	}
	// 重命名文件夹
	function wp_qiniu_folder_rename( $id, $newname)
	{
		global $wpdb, $bucketMgr;
		$table_name = $wpdb->prefix.'wp_qiniu_files';
		$row = $wpdb->get_row($wpdb->prepare("SELECT pid,fname FROM $table_name WHERE id=%d and isdir=1",$id), ARRAY_A);
		if(!$row)
			return array('status' => 'false', 'error' => '获取文件夹信息错误！'.(empty($row)?'文件夹不存在。':$wpdb->last_error));
		$pid = $row['pid'];
		$oldname = $row['fname'];
		$check = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE pid=%d AND id<>%d AND fname=%s and isdir=1",$pid, $id,$newname));
		if($check){
			return array('status' => 'false', 'error' => '已存在相同名称的文件夹！');
		}else if($check === false){
			$err = $wpdb->last_error;
			return array('status' => 'false', 'error' => $err);
		}else {
			$fpath = wp_qiniu_get_prefix_by_pid($pid);
			if($fpath === false || $fpath === null)
				return array('status' => 'false', 'error' => '获取文件路径错误！'.(($fpath === false)?$wpdb->last_error:''));
			$oldprefix = $fpath . $oldname.'/';
			$newprefix = $fpath . $newname.'/';

			// 获取所有以 $oldprefix 开头的文件
			$filelists = array();
			$marker = null;
			do {
				list( $retFiles, $marker, $err ) = $bucketMgr->listFiles( WP_QINIU_STORAGE_BUCKET, $oldprefix, $marker );
				if ( $err !== null ) {
					return array( 'status' => 'false', 'error' => '获取七牛云存储文件列表失败：' . $err->message() );
				} else {
					$filelists = array_merge( $filelists, $retFiles['items'] );
				}
			} while ( $marker );

			// 修改七牛文件名
			$rename = array();
			$oldLen = strlen( $oldprefix );
			foreach ( $filelists as $file ) {
				$newkey = $newprefix . substr( $file['key'], $oldLen );
				$rename[$file['key']] = $newkey;
			}
			$renameOp = $bucketMgr->buildBatchRename( WP_QINIU_STORAGE_BUCKET, $rename );
			list($ret, $err) = $bucketMgr->batch( $renameOp );
			if($err !== null)
				return array( 'status' => 'false', 'error' => '重命名七牛云存储文件失败：'.$err->code().'-'.$err->message() );
			$ok = true;
			foreach ($ret as $fileret){
				if($fileret['code'] !== 200 && $fileret['code'] !== 612)
					$ok &= false;
			}
			if(!$ok)
				return array( 'status' => 'false', 'error' => '七牛云存储部分文件未能完成重命名操作，请重做此操作，否则将造成网站记录与存储文件名不一致的情况。' );
			// 更新数据库记录
			$ok = $wpdb->update( $table_name, array( 'fname' => $newname ), array( 'id' => $id ), array( '%s' ), array( '%u' ) );
			if ( ! $ok ) {
				$err = $wpdb->last_error;
				return array(
					'status' => 'false',
					'error'  => '七牛云存储文件重命名完成，但数据库记录修改失败。请重做此操作，否则将造成网站记录与存储文件名不一致的情况。' . $err
				);
			} else {
				return array( 'status' => 'success' );
			}
		}
	}

	// 同步文件信息
	function wp_qiniu_file_sync_by_pid($pid){
		global $wpdb, $bucketMgr;
		$table_name = $wpdb->prefix.'wp_qiniu_files';
		$fpath = wp_qiniu_get_prefix_by_pid($pid);
		// 列举文件
		$marker = null;
		$ctime = date('Y-m-d H:i:s',time());
		do {
			list( $ret, $marker, $err ) = $bucketMgr->listFiles( WP_QINIU_STORAGE_BUCKET, $fpath, $marker, 1000, '/' );
			if ( $err !== null ) {
				return array( 'status' => 'false', 'error' => $err->message() );
			}
			foreach ($ret['commonPrefixes'] as $dir){   //  同步文件夹信息
				$fname = explode('/', rtrim($dir,'/'));
				$fname = $fname[count($fname) - 1];
				$ok = $wpdb->update( $table_name, array( 'flag' => 1 ), array( 'pid' => $pid, 'fname' => $fname, 'isdir' => 1 ), array( '%d' ), array('%u', '%s', '%d' ) );
				if($ok === false){
					$err = $wpdb->last_error;
					return array('status' => 'false', 'error'  => '同步失败！' . $err);
				}elseif($ok === 0) {
					$ok = $wpdb->insert( $table_name,
						array( 'pid' => $pid, 'isdir' => 1, 'fname' => $fname, 'fsize' => 0, 'ctime' => $ctime, 'width' => 0, 'height' => 0, 'mimeType' => '', 'flag' => 1 ),
						array( '%u', '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%d' ) );
					if ( ! $ok ) {
						$err = $wpdb->last_error;
						return array( 'status' => 'false', 'error' => '同步失败！' . $err );
					} else {
						$id = $wpdb->insert_id;
					}
				} else {
					$id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE pid=%d AND fname=%s and isdir=1",$pid, $fname));
					if($id === false){
						$err = $wpdb->last_error;
						return array('status' => 'false', 'error' => $err);
					}
				}
				$result = wp_qiniu_file_sync_by_pid($id);
				if($result['status'] !== 'success')
					return $result;
			}
			foreach ($ret['items'] as $file){   //  同步文件信息
				$fname = explode('/', $file['key']);
				$fname = $fname[count($fname) - 1];
				$ok = $wpdb->update( $table_name, array( 'flag' => 1 ), array( 'pid' => $pid, 'fname' => $fname, 'isdir' => 0 ), array( '%d' ), array('%u', '%s', '%d' ) );
				if($ok === false){
					$err = $wpdb->last_error;
					return array('status' => 'false', 'error'  => '同步失败！' . $err);
				}elseif($ok === 0) {
					$ctime = date('Y-m-d H:i:s', $file['putTime']/10000000);
					$ok = $wpdb->insert( $table_name,
						array( 'pid' => $pid, 'isdir' => 0, 'fname' => $fname, 'fsize' => $file['fsize'], 'ctime' => $ctime, 'width' => -1, 'height' => -1, 'mimeType' => $file['mimeType'], 'flag' => 1 ),
						array( '%u', '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%d' ) );
					if ( ! $ok ) {
						$err = $wpdb->last_error;
						return array( 'status' => 'false', 'error' => '同步失败！' . $err );
					} else {
						$id = $wpdb->insert_id;
					}
				}
			}
		}
		while($marker !== null);
		// 删除不存在的文件及文件夹信息
		$dels = $wpdb->get_results("SELECT * FROM `$table_name` where pid=$pid and flag=0;", ARRAY_A);
		$ok = true;
		foreach($dels as $childFile){
			$ok &= wp_qiniu_delete_file($table_name, $childFile['id'], $fpath.'/'.$childFile['fname'], $childFile['isdir']);
		}
		$wpdb->update( $table_name, array( 'flag' => 0 ), array('flag' => 1, 'pid' => $pid ), array( '%d' ), array('%d', '%u') );
		if ($ok)
			return array( 'status' => 'success' );
		else
			return array( 'status' => 'false', 'error' => '删除不存在的文件信息失败！' );
	}
	/*
	// 替换字符串中第一次出现的子串
		function str_replace_first($find, $replace, $string)
		{
			$position = strpos($string, $find);
			if ($position !== false) {
				$length = strlen($find);
				$string = substr_replace($string, $replace, $position, $length);
				return $string;
			} else {
				return $string;
			}
		}

	// 获取当前访问的URL地址
		function wp_qiniu_current_request_url($query = array(), $remove = array())
		{
			// 获取当前URL
			$current_url = 'http';
			if ($_SERVER["HTTPS"] == "on") {
				$current_url .= "s";
			}
			$current_url .= "://";
			// 部分主机会出现多出端口号的情况，我们把它注释掉，看还会不会出现这种情况。
			//if($_SERVER["SERVER_PORT"] != "80"){
			//	$current_url .= WP2PCS_SITE_DOMAIN.":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
			//}else{
			$current_url .= WP_QINIU_SITE_DOMAIN . $_SERVER["REQUEST_URI"];
			//}
			// 是否要进行参数处理
			$parse_url = parse_url($current_url);
			if (is_array($query) && !empty($query)) {
				parse_str($parse_url['query'], $parse_query);
				$parse_query = array_merge($parse_query, $query);
				if (!empty($remove)) foreach ($remove as $key) {
					if (isset($parse_query[$key])) unset($parse_query[$key]);
				}
				$parse_query = http_build_query($parse_query);
				$current_url = str_replace($parse_url['query'], '?' . $parse_query, $current_url);
			} elseif ($query === false) {
				$current_url = str_replace('?' . $parse_url['query'], '', $current_url);
			}
			return $current_url;
		}
		*/
	// 创建一个函数，用来获取当前PHP的执行时间
	function wp_qiniu_get_unix_timestamp()
	{
		list($msec, $sec) = explode(' ', microtime());
		return (float)$sec + (float)$msec;
	}

	// 利用上面的函数，获取php开始执行的时间戳。注意，这是一个全局函数
	$wp_qiniu_begin_run_time = wp_qiniu_get_unix_timestamp();

	// 创建一个函数，获取php执行了的时间，以秒为单位（浮点数）
	function wp_qiniu_get_run_time()
	{
		global $wp_qiniu_begin_run_time;
		$php_run_time = wp_qiniu_get_unix_timestamp() - $wp_qiniu_begin_run_time;
		return $php_run_time;
	}

	// 替换字符串中最后一次出现的子串
	function str_replace_last($find, $replace, $string)
	{
		$position = strrpos($string, $find);
		if ($position !== false) {
			$length = strlen($find);
			$string = substr_replace($string, $replace, $position, $length);
			return $string;
		} else {
			return $string;
		}
	}

// 创建一个函数，判断wordpress是否安装在子目录中
	function get_blog_install_in_subdir()
	{
		// 获取home_url其中的path部分，以此来判断是否安装在子目录中
		$install_in_sub_dir = parse_url(home_url(), PHP_URL_PATH);
		if ($install_in_sub_dir) {
			return $install_in_sub_dir;
		} else {
			return false;
		}
	}

// 判断wordpress是否安装在win主机，并开启了重写
	function get_blog_install_software()
	{
		$software = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '';
		if (strpos($software, 'IIS') !== false) {
			$software = 'IIS';
		} elseif (strpos($software, 'Apache') !== false) {
			$software = 'Apache';
		} elseif (strpos($software, 'NginX') !== false) {
			$software = 'NginX';
		} else {
			$software = 'Others';
		}
		// 先判断这个主机的服务器软件
		return $software;
	}

	// 用下面这个函数判断wordpress是否已经开启重写，并且返回开启重写的方式
	function is_wp_rewrited()
	{
		$is_rewrited = false;
		$software = get_blog_install_software();
		$permalink_structure = get_option('permalink_structure');
		$install_root = ABSPATH;
		$install_in_subdir = get_blog_install_in_subdir();
		if ($install_in_subdir) {
			$install_root = str_replace_last($install_in_subdir . '/', '', $install_root);
		}
		if ($permalink_structure) {
			$is_rewrited = "$permalink_structure ";
			$install_root = trim($install_root);
			if (file_exists($install_root . '.htaccess'))
				$is_rewrited .= '.htaccess ';
			if (file_exists($install_root . 'httpd.conf'))
				$is_rewrited .= 'httpd.conf ';
			if (file_exists($install_root . 'app.conf'))
				$is_rewrited .= 'app.conf ';
			if (file_exists($install_root . 'config.yaml'))
				$is_rewrited .= 'config.yaml ';
			if (file_exists($install_root . 'httpd.ini'))
				$is_rewrited .= 'httpd.ini';
		}
		return $is_rewrited;
	}

// 判断文件或目录是否真的有可写权限
// http://blog.csdn.net/liushuai_andy/article/details/8611433
	function wp_qiniu_is_really_writable($file)
	{
		$file = trim($file);
		// 是否开启安全模式
		if (DIRECTORY_SEPARATOR == '/' AND @ini_get("safe_mode") == FALSE) {
			return is_writable($file);
		}
		// 如果是目录的话
		if (is_dir($file)) {
			$file = rtrim($file, '/') . '/' . md5(mt_rand(1, 100) . mt_rand(1, 100));
			if (($fp = @fopen($file, 'w+')) === FALSE) {
				return FALSE;
			}
			fclose($fp);
			@chmod($file, '0755');
			@unlink($file);
			return TRUE;
		} // 如果是不是文件，或文件打不开的话
		elseif (!is_file($file) OR ($fp = @fopen($file, 'w+')) === FALSE) {
			return FALSE;
		}
		fclose($fp);
		return TRUE;
	}
	function wp_qiniu_get_real_path($path)
	{/*
		if (DIRECTORY_SEPARATOR == '\\') {
			return str_replace('/', '\\', $path);
		} else {
			return str_replace('\\', '/', $path);
		}*/
		while(stripos($path,'\\\\') !== false){
			$path = str_replace('\\\\','/',$path);
		}
		while(stripos($path,'//') !== false){
			$path = str_replace('//','/',$path);
		}
		$path = str_replace('/',DIRECTORY_SEPARATOR,$path);
		return $path;
	}

// 解决路径最后的slah尾巴，如果没有则加上，而且根据不同的服务器，采用/或者\
	function wp_qiniu_trailing_slash_path($path_string)
	{
		$trail = substr($path_string, -1);
		if ($trail != '/' && $trail != '\\') {
			$path_string .= DIRECTORY_SEPARATOR;
		}
		else
			$path_string = substr($path_string,0,-1).DIRECTORY_SEPARATOR;
		return $path_string;
	}

	function get_by_curl($url, $post = false)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		if ($post) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}

		if (strtolower(substr($url, 0, 5)) == 'https') {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// 对认证证书来源的检查
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);// 从证书中检查SSL加密算法是否存在
			curl_setopt($ch, CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_LEAST_RECENTLY_USED);
		}
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, false);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 设置超时限制防止死循环
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);

		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

// 设置全局参数

	function set_php_setting($name)
	{
		if ($name == 'session_start') {
			/* 为了兼容性，去掉session，你可以自己打开
            if(defined('WP_TEMP_DIR') && is_really_writable(WP_TEMP_DIR)){
                if(function_exists('ini_set'))ini_set('session.save_path',WP_TEMP_DIR);// 重新规定session的存储位置
            }
            session_start();
            */
		} elseif ($name == 'session_end') {
			/* 为了兼容性，去掉session，你可以自己打开
            if(function_exists("session_destroy"))session_destroy();
            */
		} elseif ($name == 'limit') {
			/* 为了兼容性，去掉time limit，你可以自己打开*/
			if (function_exists("ini_set")) {
				//ini_set('memory_limit','128M'); // 扩大内存限制，防止备份溢出
				ini_set('max_execution_time', 600);
			}
			if (function_exists('set_time_limit')) set_time_limit(36000); // 延长执行时间，防止备份失败
			if (function_exists('ignore_user_abort')) ignore_user_abort(true);
		} elseif ($name == 'timezone') {
			date_default_timezone_set("PRC");// 使用东八区时间，如果你是其他地区的时间，自己修改
		} elseif ($name == 'error') {
			// 显示运行错误
			if (function_exists('error_reporting')) error_reporting(E_ALL);
			if (function_exists('ini_set')) ini_set('display_errors', 1);
		}
	}
}