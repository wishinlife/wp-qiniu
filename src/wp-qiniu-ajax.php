<?php
// 处理用户上传文件时ajax获取上传token
add_action('wp_ajax_nopriv_wp_qiniu_get_uptoken','wp_qiniu_get_uptoken_nopriv');
function wp_qiniu_get_uptoken_nopriv(){	//用户未登录，返回401错误
	header("HTTP/1.1 401 Unauthorized");
	header('status: 401 Unauthorized');
	exit;
}
add_action('wp_ajax_wp_qiniu_get_uptoken','wp_qiniu_get_uptoken_ajax');
function wp_qiniu_get_uptoken_ajax(){
	check_ajax_referer('wp_qiniu_ajax_nonce', 'nonce');
	
	$bucket = WP_QINIU_STORAGE_BUCKET;// 要上传的空间
	$scope = $bucket;
	if(isset($_REQUEST['pid']) && is_numeric($_REQUEST['pid']) && $_REQUEST['pid'] >= 0){
		$pid = (int)$_REQUEST['pid'];
	}else{
		$resp = array('status' => 'failed','error' => '必须指定父级文件夹！');
		echo json_encode($resp);
		exit;
	}
	if(isset($_REQUEST['fname']) && !empty($_REQUEST['fname'])){
		$fname = escape_file_name($_REQUEST['fname']);
        if($fname == ''){
            $resp = array('status' => 'failed','error' => '文件名不合要求！');
            echo json_encode($resp);
            exit;
        }
	}else{
		$resp = array('status' => 'failed','error' => '文件名不能为空！');
		echo json_encode($resp);
		exit;
	}
	$fpath = wp_qiniu_get_prefix_by_pid($pid);
	if($fpath === false || $fpath === null){
		$resp = array('status' => 'failed','error' => '获取文件路径错误！');
		echo json_encode($resp);
		exit;
	}
	$key = $fpath.$fname;
	$scope .= ':'.$key;

	global $wpdb;
	$table_name = $wpdb->prefix.'wp_qiniu_files';
	$check = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE pid=%d AND fname=%s and isdir=0",$pid,$fname));
	if($check){
		$exist = true;
	}else {
		$exist = false;
	}

	//set_php_setting('timezone');
	$expires = 10800;
	$deadline = time() + $expires;

	$fileext = strtolower(substr($fname,strrpos($fname,'.') + 1));
	$filetype = 'other';
	if(in_array($fileext,array('jpg','jpeg','png','gif','bmp'))){
		$filetype = 'image';
	}
	// 判断是否为视频
	elseif(in_array($fileext,array('asf','avi','flv','mkv','mov','mp4','wmv','3gp','3g2','mpeg','ts','rm','rmvb','m3u8'))){
		$filetype = 'video';
	}

	// 上传文件到七牛后， 七牛将文件名和文件大小回调给业务服务器.
	// 可参考文档: http://developer.qiniu.com/docs/v6/api/reference/security/put-policy.html
    if(WP_QINIU_NOT_CALLBACK) {
        $url = wp_qiniu_get_download_url($key, 14400);
        if($filetype == 'image'){
            $returnBody = '{"ncb":' . WP_QINIU_NOT_CALLBACK . ',"url":"' . $url . '", "pid": ' . $pid . ', "fname": "' . $fname . '", "fsize": $(fsize), "key": $(key), "hash": $(etag), "ctime": ' . time()
                . ', "format": $(imageInfo.format), "width": $(imageInfo.width), "height": $(imageInfo.height), "mimeType": $(mimeType),"overwrite":$(x:overwrite)}';        //直接返回给上传端，不回调服务器
        } elseif($filetype == 'video') {
            $returnBody = '{"ncb":' . WP_QINIU_NOT_CALLBACK . ',"url":"' . $url . '", "pid": ' . $pid . ', "fname": "' . $fname . '", "fsize": $(fsize), "key": $(key), "hash": $(etag), "ctime": ' . time()
                . ', "format": $(avinfo.format.format_long_name), "width": $(avinfo.video.width), "height": $(avinfo.video.height), "mimeType": $(mimeType),"overwrite":$(x:overwrite)}';        //直接返回给上传端，不回调服务器
        } else {
            $returnBody = '{"ncb":' . WP_QINIU_NOT_CALLBACK . ',"url":"' . $url . '", "pid": ' . $pid . ', "fname": "' . $fname . '", "fsize": $(fsize),"format":"", "key": $(key), "hash": $(etag), "ctime": ' . time() . ', "mimeType": $(mimeType),"width":0,"height":0,"overwrite":$(x:overwrite)}';    //直接返回给上传端，不回调服务器
        }
        $policy = array(
            'scop' => $scope,
            'deadline' => $deadline,
            'returnBody' => $returnBody
        );
    } else {
        if($filetype == 'image'){
            $callBackBody = 'pid='.$pid.'&fsize=$(fsize)&format=$(imageInfo.format)&width=$(imageInfo.width)&height=$(imageInfo.height)&mimeType=$(mimeType)&key=$(key)&hash=$(etag)';
        } elseif($filetype == 'video') {
            $callBackBody = 'pid='.$pid.'&fsize=$(fsize)&format=$(avinfo.format.format_long_name)&width=$(avinfo.video.width)&height=$(avinfo.video.height)&mimeType=$(mimeType)&key=$(key)&hash=$(etag)';
        } else {
            $callBackBody = 'pid='.$pid.'&fsize=$(fsize)&format=&width=0&height=0&mimeType=$(mimeType)&key=$(key)&hash=$(etag)';
        }
        $policy = array(
            'scop' => $scope,
            'deadline' => $deadline,
            'callbackBodyType' => 'application/x-www-form-urlencoded',
            'callbackUrl' => admin_url('admin-ajax.php?action=wp_qiniu_upload_callback'),
            'callbackBody' => $callBackBody
        );
    }
	global $auth;
	$uptoken = $auth->uploadToken($bucket, $key, $expires, $policy);
	$ret = array('status' => 'success','uptoken' => $uptoken, 'fname' => $fname, 'exist' => $exist);
	header('Content-Type: application/json');
	echo json_encode($ret);
	exit;
}

// 处理上传完成后，七牛服务器回调的，不需要用户登录
add_action('wp_ajax_nopriv_wp_qiniu_upload_callback','wp_qiniu_upload_callback_ajax');
add_action('wp_ajax_wp_qiniu_upload_callback','wp_qiniu_upload_callback_ajax');
function wp_qiniu_upload_callback_ajax() {
	//获取回调的body信息
	$callbackBody = file_get_contents('php://input');
	//回调的contentType
	$contentType = 'application/x-www-form-urlencoded';
	//回调的签名信息，可以验证该回调是否来自七牛
	$authorization = $_SERVER['HTTP_AUTHORIZATION'];
	//七牛回调的url，具体可以参考：http://developer.qiniu.com/docs/v6/api/reference/security/put-policy.html

	//$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
	$url = admin_url('admin-ajax.php?action=wp_qiniu_upload_callback');

	global $auth;
	$isQiniuCallback = $auth->verifyCallback($contentType, $authorization, $url, $callbackBody);

	header('Content-Type: application/json');
	if ($isQiniuCallback) {
		//$resp = array('ret' => 'success');
		//客户端上传文件成功，且回调正确
		parse_str($callbackBody);
        $fname = basename($key);

		set_php_setting('timezone');
		$ctime = date('Y-m-d H:i:s',time());

		global $wpdb;
		$table_name = $wpdb->prefix.'wp_qiniu_files';
		$ok = $wpdb->replace($table_name,array('pid' => $pid, 'isdir' => 0, 'fname' => $fname, 'fsize' => $fsize, 'ctime' => $ctime, 'width' => $width, 'height' => $height, 'mimeType' => $mimeType),
			array('%d', '%d', '%s', '%d', '%s', '%d', '%d', '%s'));
		$id = $wpdb->insert_id;

		if (!$ok)
		{
			$err = $wpdb->last_error;
			$resp = array('status' => 'false', 'error' => $err);
			echo json_encode($resp);
			exit;
		}
		$url = wp_qiniu_get_download_url($key, 3600);
		$resp = array('status' => 'success','id' => $id, 'pid' => $pid, 'isdir' => 0, 'fname' => $fname, 'fsize' => $fsize, 'mimeType' => $mimeType,
			'ctime' => $ctime, 'format' => $format, 'width' => $width, 'height' => $height, 'key' => $key, 'hash' => $hash, 'url' => $url);
	} else {
		$resp = array('status' => 'failed', 'error' => '回调验证失败。', 'ret' => 'failed');
	}
	echo json_encode($resp);
	exit;
}
function wp_qiniu_upload_callback_ajax2() {
	//  http://www.***.com/wp-content/plugins/wp-qiniu/callback.txt
	$filename=dirname(__FILE__).'/callback.txt';
	if ($fh = fopen($filename, "a")) {
		fwrite($fh, "Start CallBack Process.\r\n");

		//获取回调的body信息
		$callbackBody = file_get_contents('php://input');
		fwrite($fh, 'CallBackBody:'.$callbackBody."\r\n");
		//回调的contentType
		$contentType = 'application/x-www-form-urlencoded';
		//回调的签名信息，可以验证该回调是否来自七牛
		$authorization = $_SERVER['HTTP_AUTHORIZATION'];
		fwrite($fh, 'authorization:'.$authorization."\r\n");
		//七牛回调的url，具体可以参考：http://developer.qiniu.com/docs/v6/api/reference/security/put-policy.html

		//$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
		$url = admin_url('admin-ajax.php?action=wp_qiniu_upload_callback');

		global $auth;
		$isQiniuCallback = $auth->verifyCallback($contentType, $authorization, $url, $callbackBody);
		fwrite($fh, 'isQiniuCallback:'.(string)$isQiniuCallback."\r\n");

		header('Content-Type: application/json');
		if ($isQiniuCallback) {
			//$resp = array('ret' => 'success');
			//客户端上传文件成功，且回调正确
			parse_str($callbackBody);

			fwrite($fh, 'key:'.$key."\r\n");

			$fname = basename($key);

			fwrite($fh, 'pid:'.(string)$pid."\r\n");
			fwrite($fh, 'fname:'.$fname."\r\n");
			fwrite($fh, 'fsize:'.(string)$fsize."\r\n");
			fwrite($fh, 'width:'.(string)$width."\r\n");
			fwrite($fh, 'height:'.(string)$height."\r\n");
			fwrite($fh, 'mimeType:'.$mimeType."\r\n");
			fwrite($fh, 'format:'.$format."\r\n");
			fwrite($fh, 'hash:'.$hash."\r\n");

			set_php_setting('timezone');
			$ctime = date('Y-m-d H:i:s',time());
			fwrite($fh, 'ctime:'.$ctime."\r\n");

			global $wpdb;
			$table_name = $wpdb->prefix.'wp_qiniu_files';
			$ok = $wpdb->replace($table_name,array('pid' => $pid, 'isdir' => 0, 'fname' => $fname, 'fsize' => $fsize, 'ctime' => $ctime, 'width' => $width, 'height' => $height, 'mimeType' => $mimeType),
				array('%d', '%d', '%s', '%d', '%s', '%d', '%d', '%s'));
			$id = $wpdb->insert_id;

			fwrite($fh, 'ins_id:'.(string)$id."\r\n");
			if (!$ok)
			{
				$err = $wpdb->last_error;
				fwrite($fh, 'ins_err:'.$err."\r\n");
				$resp = array('status' => 'false', 'error' => $err);
				echo json_encode($resp);
				exit;
			}
			$url = wp_qiniu_get_download_url($key, 3600);
			$resp = array('status' => 'success','id' => $id, 'pid' => $pid, 'isdir' => 0, 'fname' => $fname, 'fsize' => $fsize, 'mimeType' => $mimeType,
				'ctime' => $ctime, 'format' => $format, 'width' => $width, 'height' => $height, 'key' => $key, 'hash' => $hash, 'url' => $url);
		} else {
			$resp = array('status' => 'failed', 'error' => '回调验证失败。');
		}
		fwrite($fh, "\r\n++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++\r\n\r\n");
		fclose($fh);
		echo json_encode($resp);
	}
	exit;
}

// 客户端上传文件成功，提交文件信息
add_action('wp_ajax_wp_qiniu_upload_complete','wp_qiniu_upload_complete');
function wp_qiniu_upload_complete(){
    // 获取文件信息
    if(isset($_REQUEST['pid']) && is_numeric($_REQUEST['pid']) && $_REQUEST['pid'] >= 0){
        $pid = (int)$_REQUEST['pid'];
    }else{
        $resp = array('status' => 'failed','error' => '必须指定父级文件夹！');
        echo json_encode($resp);
        exit;
    }
    if(isset($_REQUEST['fname']) && !empty($_REQUEST['fname'])){
        $fname = $_REQUEST['fname'];
        if($fname == ''){
            $resp = array('status' => 'failed','error' => '文件名不合要求！');
            echo json_encode($resp);
            exit;
        }
    }else{
        $resp = array('status' => 'failed','error' => '文件名不能为空！');
        echo json_encode($resp);
        exit;
    }
    if(isset($_REQUEST['fsize']) && is_numeric($_REQUEST['fsize']) && $_REQUEST['fsize'] >= 0){
        $fsize = (int)$_REQUEST['fsize'];
    }else{
        $resp = array('status' => 'failed','error' => '必须给定文件大小！');
        echo json_encode($resp);
        exit;
    }
    if(isset($_REQUEST['width']) && is_numeric($_REQUEST['width']) && $_REQUEST['width'] >= 0){
        $width = (int)$_REQUEST['width'];
    }else{
        $resp = array('status' => 'failed','error' => '图像宽异常！');
        echo json_encode($resp);
        exit;
    }
    if(isset($_REQUEST['height']) && is_numeric($_REQUEST['height']) && $_REQUEST['height'] >= 0){
        $height = (int)$_REQUEST['height'];
    }else{
        $resp = array('status' => 'failed','error' => '图像高异常！');
        echo json_encode($resp);
        exit;
    }
    if(isset($_REQUEST['mimeType'])){
        $mimeType = $_REQUEST['mimeType'];
    }else{
        $resp = array('status' => 'failed','error' => 'mimeType信息异常！');
        echo json_encode($resp);
        exit;
    }
    if(isset($_REQUEST['key']) && !empty($_REQUEST['key'])){
        $key = $_REQUEST['key'];
        if($key == ''){
            $resp = array('status' => 'failed','error' => '文件Key不合要求！');
            echo json_encode($resp);
            exit;
        }
    }else{
        $resp = array('status' => 'failed','error' => '文件Key不能为空！');
        echo json_encode($resp);
        exit;
    }
    if(isset($_REQUEST['format'])){
        $format = $_REQUEST['format'];
    }else{
        $resp = array('status' => 'failed','error' => '文件格式信息异常！');
        echo json_encode($resp);
        exit;
    }
    if(isset($_REQUEST['hash'])){
        $hash = $_REQUEST['hash'];
    }else{
        $resp = array('status' => 'failed','error' => '文件hash信息异常！');
        echo json_encode($resp);
        exit;
    }

    header('Content-Type: application/json');

    set_php_setting('timezone');
    $ctime = date('Y-m-d H:i:s',time());

    global $wpdb;
    $table_name = $wpdb->prefix.'wp_qiniu_files';
    $ok = $wpdb->replace($table_name,array('pid' => $pid, 'isdir' => 0, 'fname' => $fname, 'fsize' => $fsize, 'ctime' => $ctime, 'width' => $width, 'height' => $height, 'mimeType' => $mimeType),
        array('%d', '%d', '%s', '%d', '%s', '%d', '%d', '%s'));
    $id = $wpdb->insert_id;

    if (!$ok)
    {
        $err = $wpdb->last_error;
        $resp = array('status' => 'false', 'error' => $err);
        echo json_encode($resp);
        exit;
    }
    $url = wp_qiniu_get_download_url($key, 3600);
    $resp = array('status' => 'success','id' => $id, 'pid' => $pid, 'isdir' => 0, 'fname' => $fname, 'fsize' => $fsize, 'mimeType' => $mimeType,
        'ctime' => $ctime, 'format' => $format, 'width' => $width, 'height' => $height, 'key' => $key, 'hash' => $hash, 'url' => $url);

    echo json_encode($resp);
    exit;
}

// 用户端获取文件下载链接
add_action('wp_ajax_nopriv_wp_qiniu_get_download_url','wp_qiniu_get_download_url_nopriv');
function wp_qiniu_get_download_url_nopriv(){		// 用户未登录，返回401错误
	header("HTTP/1.1 401 Unauthorized");
	header('status: 401 Unauthorized');
	exit;
}
add_action('wp_ajax_wp_qiniu_get_download_url','wp_qiniu_get_download_url_ajax');
function wp_qiniu_get_download_url_ajax(){
	check_ajax_referer('wp_qiniu_ajax_nonce', 'nonce');
	if(isset($_REQUEST['key']) && !empty($_REQUEST['key'])){
		$key = escape_file_name($_REQUEST['key']);
	}else {
		$resp = array('status' => 'failed','error' => '下载文件的key不能为空！');
		echo json_encode($resp);
		exit;
	}
	$url = wp_qiniu_get_download_url($key);
	$resp = array('status' => 'success', 'url' => $url);
	echo json_encode($resp);
	exit;
}

// 用户端获取文件列表（wordpress中存储的文件信息，而非七牛云存储中的文件信息）
add_action('wp_ajax_nopriv_wp_qiniu_list_files','wp_qiniu_list_files_nopriv');
function wp_qiniu_list_files_nopriv(){		// 用户未登录，返回401错误
	header("HTTP/1.1 401 Unauthorized");
	header('status: 401 Unauthorized');
	exit;
}
add_action('wp_ajax_wp_qiniu_list_files','wp_qiniu_list_files_ajax');
function wp_qiniu_list_files_ajax() {
	check_ajax_referer('wp_qiniu_ajax_nonce', 'nonce');
	if(isset($_REQUEST['pid']) && is_numeric($_REQUEST['pid']) && $_REQUEST['pid'] >= 0){
		$pid = (int)$_REQUEST['pid'];
	}else{
		$resp = array('status' => 'failed','error' => '必须指定父级文件夹！');
		echo json_encode($resp);
		exit;
	}
	if(isset($_REQUEST['paged']) && is_numeric($_REQUEST['paged']) && $_REQUEST['paged'] > 1){
		$paged = (int)$_REQUEST['paged'];
	}else{
		$paged = 1;
	}
	if(isset($_REQUEST['pagesize']) && is_numeric($_REQUEST['pagesize']) && $_REQUEST['pagesize'] > 1){
		$pagesize = (int)$_REQUEST['pagesize'];
	}else{
		$pagesize = 100;
	}
	if(isset($_REQUEST['orderby']) && !empty($_REQUEST['orderby'])){
		$orderby = sanitize_sql_orderby(str_replace('-',' ',$_REQUEST['orderby']));
    }else{
		$orderby = 'ctime desc';
    }
	$limit = ($paged-1)*$pagesize.','.$paged*$pagesize;

	global $wpdb;
	$table_name = $wpdb->prefix.'wp_qiniu_files';
	$results = $wpdb->get_results("select * from `$table_name` where pid=$pid order by isdir desc, $orderby limit $limit;", ARRAY_A);

	header('Content-Type: application/json');
	if (!$results && !empty($results)) {
		$err = $wpdb->last_error;
		$resp = array('status' => 'false', 'error' => $err);
		echo json_encode($resp);
	}else{
		$resp = array('status' => 'success', 'data' => $results);
		echo json_encode($resp);
	}
	exit;
}

// 新建文件夹
add_action('wp_ajax_nopriv_wp_qiniu_create_floders','wp_qiniu_create_floder_nopriv');
function wp_qiniu_create_floder_nopriv(){		// 用户未登录，返回401错误
	header("HTTP/1.1 401 Unauthorized");
	header('status: 401 Unauthorized');
	exit;
}
add_action('wp_ajax_wp_qiniu_create_floder', 'wp_qiniu_create_floder_ajax');
function wp_qiniu_create_floder_ajax() {
	check_ajax_referer('wp_qiniu_ajax_nonce', 'nonce');
	if(isset($_REQUEST['pid']) && is_numeric($_REQUEST['pid']) && $_REQUEST['pid'] >= 0){
		$pid = (int)$_REQUEST['pid'];
	}else{
		$resp = array('status' => 'failed','error' => '必须指定父级文件夹！');
		echo json_encode($resp);
		exit;
	}
	if(isset($_REQUEST['dirname']) && !empty($_REQUEST['dirname'])){
		$fname = escape_file_name($_REQUEST['dirname']);
        if($fname == ''){
            $resp = array('status' => 'failed','error' => '文件名不合要求！');
            echo json_encode($resp);
            exit;
        }
	}else{
		$resp = array('status' => 'failed','error' => '文件夹名称不能为空！');
		echo json_encode($resp);
		exit;
	}

	set_php_setting('timezone');
	$ctime = date('Y-m-d H:i:s',time());

	header('Content-Type: application/json');
	global $wpdb;
	$table_name = $wpdb->prefix.'wp_qiniu_files';
	$check = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE pid=%d AND fname=%s and isdir=1",$pid,$fname));
	if($check){
		$resp = array('status' => 'false', 'error' => '已存在相同名称的文件夹！');
		echo json_encode($resp);
	}else if($check === false){
		$err = $wpdb->last_error;
		$resp = array('status' => 'false', 'error' => $err);
		http_response_code(500);
		echo json_encode($resp);
	}else {
		$ok = $wpdb->insert($table_name,
			array('pid' => $pid, 'isdir' => 1, 'fname' => $fname, 'fsize' => 0, 'ctime' => $ctime, 'width' => 0, 'height' => 0, 'mimeType' => ''),
			array('%d', '%d', '%s', '%d', '%s', '%d', '%d', '%s'));

		if (!$ok) {
			$err = $wpdb->last_error;
			$resp = array('status' => 'false', 'error' => $err);
			echo json_encode($resp);
		} else {
			$id = $wpdb->insert_id;
			$resp = array('status' => 'success', 'id' => $id, 'pid' => $pid, 'isdir' => 1, 'fname' => $fname, 'fsize' => 0, 'ctime' => $ctime, 'width' => 0, 'height' => 0, 'mimeType' => '');
			echo json_encode($resp);
		}
	}
	exit;
}

// 删除文件或文件夹夹
add_action('wp_ajax_nopriv_wp_qiniu_delete','wp_qiniu_delete_nopriv');
function wp_qiniu_delete_nopriv(){		// 用户未登录，返回401错误
	header("HTTP/1.1 401 Unauthorized");
	header('status: 401 Unauthorized');
	exit;
}
add_action('wp_ajax_wp_qiniu_delete_files', 'wp_qiniu_delete_files_ajax');
function wp_qiniu_delete_files_ajax() {
	check_ajax_referer('wp_qiniu_ajax_nonce', 'nonce');
	if(isset($_POST['files']) && !empty($_POST['files']) && is_array($_POST['files'])){
		$files = (array)$_POST['files']; // the array build by javascript, not user input
	}else{
		$resp = array('status' => 'failed','error' => '参数错误：未指定要删除的文件列表。');
		echo json_encode($resp);
		exit;
	}

	$rstArr = array();
	global $wpdb;
	$table_name = $wpdb->prefix.'wp_qiniu_files';

	foreach($files as $id){
		if(!is_numeric($id) || (int)$id > 0) {
			list( $isdir, $fkey ) = wp_qiniu_get_key_by_id( (int) $id );
			if ( $fkey ) {
				$rst      = wp_qiniu_delete_file( $table_name, (int) $id, $fkey, $isdir );
				$rstArr[] = array( 'id' => $id, 'result' => $rst );
			} else {
				$rstArr[] = array( 'id' => $id, 'result' => false );
			}
		}
	}
	$resp = array('status' => 'success','data' => $rstArr);
	echo json_encode($resp);
	exit;
}

// 重命名文件
add_action('wp_ajax_nopriv_wp_qiniu_file_rename','wp_qiniu_file_rename_nopriv');
function wp_qiniu_file_rename_nopriv(){		// 用户未登录，返回401错误
	header("HTTP/1.1 401 Unauthorized");
	header('status: 401 Unauthorized');
	exit;
}
add_action('wp_ajax_wp_qiniu_file_rename', 'wp_qiniu_file_rename_ajax');
function wp_qiniu_file_rename_ajax() {
	check_ajax_referer('wp_qiniu_ajax_nonce', 'nonce');
	if(isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) && $_REQUEST['id'] >= 0){
		$id = (int)$_REQUEST['id'];
	}else{
		$resp = array('status' => 'failed','error' => '参数错误：未提供待修改文件或文件夹ID！');
		echo json_encode($resp);
		exit;
	}
	if(isset($_REQUEST['newname']) && !empty($_REQUEST['newname'])){
		$newname = escape_file_name($_REQUEST['newname']);
        if($newname == ''){
            $resp = array('status' => 'failed','error' => '新名称不符合要求！');
            echo json_encode($resp);
            exit;
        }
	}else{
		$resp = array('status' => 'failed','error' => '参数错误：未提供新名称！');
		echo json_encode($resp);
		exit;
	}
	list( $isdir, $fkey ) = wp_qiniu_get_key_by_id( (int) $id );
	header('Content-Type: application/json');
	if($isdir)
		$resp = wp_qiniu_folder_rename ($id, $newname);
	else
		$resp = wp_qiniu_file_rename($id, $newname);
	$resp['fname'] = $newname;
	echo json_encode($resp);
	exit;
}

// 重命名文件
add_action('wp_ajax_nopriv_wp_qiniu_file_sync','wp_qiniu_file_sync_nopriv');
function wp_qiniu_file_sync_nopriv(){		// 用户未登录，返回401错误
	header("HTTP/1.1 401 Unauthorized");
	header('status: 401 Unauthorized');
	exit;
}
add_action('wp_ajax_wp_qiniu_file_sync', 'wp_qiniu_file_sync_ajax');
function wp_qiniu_file_sync_ajax() {
	check_ajax_referer('wp_qiniu_ajax_nonce', 'nonce');
	if(isset($_REQUEST['pid']) && is_numeric($_REQUEST['pid']) && $_REQUEST['pid'] >= 0){
		$pid = (int)$_REQUEST['pid'];
	}else{
		$resp = array('status' => 'failed','error' => '参数错误：未提供当前目录ID！');
		echo json_encode($resp);
		exit;
	}

	global $wpdb;
	$table_name = $wpdb->prefix.'wp_qiniu_files';
	$wpdb->update( $table_name, array( 'flag' => 0 ), array('flag' => 1 ), array( '%d' ), array('%d') );

	$result = wp_qiniu_file_sync_by_pid( (int) $pid );
	header('Content-Type: application/json');
	echo json_encode($result);
	exit;
}


