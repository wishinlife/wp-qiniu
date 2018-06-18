<?php

// 增加schedule,自定义的时间间隔循环的时间间隔 每周一次和每两周一次
add_filter('cron_schedules','wp_qiniu_more_reccurences_for_backup');
function wp_qiniu_more_reccurences_for_backup($schedules){
	$add_array = wp_qiniu_more_reccurences_for_backup_array();
	return array_merge($schedules,$add_array);
}

function wp_qiniu_more_reccurences_for_backup_array(){
	return array(
		'never' => array('interval' => false, 'display' => '从不'),
		'daily' => array('interval' => 3600*24, 'display' => '每天一次'),
		'doubly' => array('interval' => 3600*24*2, 'display' => '两天一次'),
		'weekly' => array('interval' => 3600*24*7, 'display' => '每周一次'),
		'biweekly' => array('interval' => 3600*24*7*2, 'display' => '两周一次'),
		'monthly' => array('interval' => 3600*24*30, 'display' => '每月一次'),
		'yearly' => array('interval' => 3600*24*30*12, 'display' => '每年一次')
	);
}

// 函数wp_qiniu_backup_corn_task_function按照规定的时间执行备份动作
add_action('wp_qiniu_backup_corn_task_database','wp_qiniu_backup_corn_task_database_function');
add_action('wp_qiniu_backup_corn_task_www','wp_qiniu_backup_corn_task_www_function');
function wp_qiniu_backup_corn_task_database_function() {
	$run_rate = get_option('wp_qiniu_backup_run_rate');
	if(!isset($run_rate['database']) || $run_rate['database'] == 'never')
		return;

	set_php_setting('limit');
	set_php_setting('timezone');

	// 备份数据库
	$file_content = "\xEF\xBB\xBF".get_database_backup_all_sql();
	$file_key = WP_QINIU_SITE_DOMAIN.'/database_'.date('Ymd_His').'.sql';

	list($ret,$err) = wp_qiniu_upload($file_content,$file_key);
	//if($err != null)
	// echo 'backup error: '.$err->message();
}

function wp_qiniu_backup_corn_task_www_function(){
	if(!WP_QINIU_IS_WRITABLE){
		return;
	}
	$run_rate = get_option('wp_qiniu_backup_run_rate');
	if(!isset($run_rate['www']) || $run_rate['www'] == 'never')
		return;
	
	$local_paths = get_option('wp_qiniu_backup_local_paths');
	if(!$local_paths || empty($local_paths)){
		$local_paths = array(ABSPATH);
	}

	set_php_setting('limit');
	set_php_setting('timezone');
	$zip_dir = wp_qiniu_trailing_slash_path(WP_QINIU_TMP_DIR);

	// 备份网站内的所有文件
	$file_name = 'www_'.date('Ymd_His').'.zip';
	$www_file = zip_files_in_dirs($local_paths, $zip_dir.$file_name, ABSPATH);
	if($www_file){
		list($ret,$err) = wp_qiniu_uploadFile($www_file, WP_QINIU_SITE_DOMAIN.'/'.$file_name);
		//if($err != null)
			// echo 'backup error: '.$err->message();
		@unlink($www_file);
	}
}

// 每天早上6:30定时清理可能由于备份失败导致的文件未删除的文件
// 未使用，在每次备份完成后不管文件是否删除成功，都删除备份文件
function wp_qiniu_backup_clear_files_task(){
	$run_time = date('Y-m-d 06:30');
	if($run_time < date('Y-m-d H:i:s')){
		$run_time = date('Y-m-d '.$run_time.':00',strtotime('+1 day'));				
	}else{
		$run_time = date('Y-m-d '.$run_time.':00');
	}
	$run_time = strtotime($run_time);	
	wp_schedule_event($run_time,'daily','wp_qiniu_backup_corn_task_clear_files');
	add_action('wp_qiniu_backup_corn_task_clear_files','wp_qiniu_backup_corn_task_clear_files_function');
}

function wp_qiniu_backup_corn_task_clear_files_function(){
	$zip_dir = wp_qiniu_trailing_slash_path(WP_QINIU_TMP_DIR);
	$dir = opendir($zip_dir);
	while($file = readdir($dir)){
		if($file == '.' || $file == '..')
			continue;
		$file_path = $zip_dir.$file;
		if(file_exists($file_path))
			@unlink($file_path);
	};
	closedir($dir);
}

/*
 *
 *  数据库备份相关函数
 *
 */
	// 获取创建某一个表的SQL
function get_database_table_structure($table){
		global $wpdb;
		$create_table = $wpdb->get_results("SHOW CREATE TABLE $table");
		if(!$create_table)
			return '';
		$table_dump = "DROP TABLE IF EXISTS $table;\n";
		$create_table = (array)$create_table[0];
		$create_table = $create_table['Create Table'];
		$table_dump .= $create_table.";";
		return $table_dump;
	}
	// 获取某一个表内的数据
	// 在BAE上，如果一次读取全部数据，数据行较多时容易备份失败
	// 手动备份设定3000行可成功备份，计划任务就不成功，因此需要根据实际来设置
	function get_database_table_records($table){
		global $wpdb;
		$limit = 0;
		$records = array();
		do{
			$table_data = $wpdb->get_results("SELECT * FROM $table limit $limit,1000",ARRAY_A);
			if(!$table_data)break;
			$search = array("\x00", "\x0a", "\x0d", "\x1a");
			$replace = array('\0', '\n', '\r', '\Z');
			if($table_data)foreach($table_data as $record){
				$values = array();
				foreach($record as $value){
					if('' === $value || $value === null){
						$values[] = "''";
					}elseif(is_numeric($value)){
						$values[] = $value;
					}else{
						$value = str_replace('\\','\\\\',$value);
						$value = str_replace('\'','\\\'',$value);
						$values[] = "'".str_replace($search,$replace,$value)."'";
					}
				}
				$records[] = "(".implode(',',$values).")";
			}
			$limit += 1000;
		}while(count($table_data)==1000);
		if(count($records)==0) return '';
		$records_dump = "INSERT INTO $table VALUES \n".implode(", \n",$records).';';
		return $records_dump;
	}
	// 获得最终需要的数据表备份SQL语句
	function get_database_backup_table_sql($table){
		$sql_table_structure = get_database_table_structure($table);
		$sql_table_data = get_database_table_records($table);
		$sql = '';
		$sql .= $sql_table_structure;
		$sql .= "\n\n";
		if(trim($sql_table_data)){
			$sql .= $sql_table_data;
			$sql .= "\n\n";
		}
		return $sql;
	}
	// 获取所有表
	function get_database_tables(){
		global $wpdb;
		$tables = $wpdb->get_results("SHOW TABLE STATUS");
		return $tables;
	}
	// 获取最终的所有SQL语句，但这样可能让文件很大，不利于导入，因此建议采用分表导出的方式（还未开发）
	function get_database_backup_all_sql(){
		$tables = get_database_tables();
		$sql = '';
		if(!empty($tables))foreach($tables as $table){
			$table = $table->Name;
			$sql .= get_database_backup_table_sql($table);
		}
		return $sql;
	}



/*
* 打包指定目录列表中的文件
* 第一个参数为准备放入zip文件的路径数组，或某单一路径
* 第二个参数为准备作为存放zip文件的路径
* 第三个参数为zip文件路径中，准备移除的路径字串
*/
	function zip_files_in_dirs($zip_local_paths,$zip_file_path,$remove_path = ''){
		if(empty($zip_local_paths)){
			return false;
		}
		$zip_file_path = trim($zip_file_path);
		if(file_exists($zip_file_path)){
			@unlink($zip_file_path);
		}
		if(!is_array($zip_local_paths)){
			if(is_string($zip_local_paths) && (is_file($zip_local_paths) || is_dir($zip_local_paths))){
				$zip_local_paths = array($zip_local_paths);
			}else{
				return false;
			}
		}
		$remove_path = rtrim(wp_qiniu_get_real_path($remove_path), DIRECTORY_SEPARATOR);

		$zip = new ZipArchive();
		if($zip->open($zip_file_path,ZipArchive::CREATE)!==TRUE){
			return false;
		}
		set_php_setting('timezone');
		foreach($zip_local_paths as $zip_local_path){
			$zip_local_path = rtrim(wp_qiniu_get_real_path(trim($zip_local_path)), DIRECTORY_SEPARATOR);
			$zip_local_path = str_replace('{year}',date('Y'),$zip_local_path);
			$zip_local_path = str_replace('{month}',date('m'),$zip_local_path);
			$zip_local_path = str_replace('{day}',date('d'),$zip_local_path);
			if(!file_exists($zip_local_path)){
				continue;
			}
			if(is_dir($zip_local_path)){
				get_files_in_dir_reset();
				$files = get_files_in_dir($zip_local_path);
				if(!empty($files))foreach($files as $file){
					$file = trim($file);
					$file_rename = str_replace($remove_path.DIRECTORY_SEPARATOR,'',$file);
					if(is_dir($file)){
						$zip->addEmptyDir($file_rename);
					}elseif(is_file($file)){
						$zip->addFile($file,$file_rename);
					}
				}
			}elseif(is_file($zip_local_path)){
				$file_rename = str_replace($remove_path.DIRECTORY_SEPARATOR,'',$zip_local_path);
				$zip->addFile($zip_local_path,$file_rename);
			}
		}
		$zip->close();

		return $zip_file_path;
	}

	// 获取目录下的文件列表，注意，参数$path末尾最好不要带/
	function get_files_in_dir($path){
		set_php_setting('limit');
		global $file_list;// 这个地方貌似有漏洞，因为之前没有声明过这个参数，这样做是否合理？
		// 经过验证，确实会遇到这个问题，即如果我两次使用get_files_in_dir函数，那么第一次中保存的$file_list将仍然存在，所以，在第一次使用完get_files_in_dir函数之后，一定要先把$file_list清空才可以。
		$path = trim($path);
		if(!file_exists($path) || !is_dir($path)){
			return null;
		}
		$dir = opendir($path);
		while($file = readdir($dir)){
			if($file == '.' || $file == '..')continue;
			$file_path = $path.DIRECTORY_SEPARATOR.$file;
			// 这个地方要注意，要排除缓存目录，不能把缓存文件也给备份了
			if(stripos($file_path,WP_QINIU_TMP_DIR) !== false){
				continue;
			}
			$file_list[] = $file_path;
			if(is_dir($file_path)){
				get_files_in_dir($file_path);
			}
		};
		closedir($dir);
		return $file_list;
	}
	// 为了上面这个函数准备的参数清空。
	function get_files_in_dir_reset(){
		global $file_list;
		$file_list = array();
	}