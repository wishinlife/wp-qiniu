<?php

/*
 * 这个文件专门为调试WP-QINIU准备，如果你的网站在使用WP-QINIU中存在什么问题，那么修改wp-qiniu.php中WP_QINIU_DEBUG为true即可知道具体是什么问题了。
 */


	// 只在前台进行调试，否则连后台都进不去了
	if(!WP_QINIU_DEBUG || is_admin()){// || !current_user_can('edit_theme_options')
		return;
	}

	// 查看服务器信息
	if(isset($_GET['phpinfo'])){
		phpinfo();
		exit;
	}

	// 显示运行错误
	error_reporting(E_ALL); 
	ini_set("display_errors", 1);

	// 输出文字
	header("Content-Type: text/html; charset=utf-8");
	
	// 测试session是否可以用
	session_start();
	echo "如果在这句话之前没有看到错误，说明session可以正常使用<br />";
	session_destroy();
	
	// 输出当前插件信息
	if(!function_exists('get_plugin_data')){
		include(ABSPATH.'./wp-admin/includes/plugin.php');
	}
	$plugin_data = get_plugin_data(WP_QINIU_PLUGIN_NAME);
	$plugin_version = $plugin_data['Version'];
	echo "插件版本号：$plugin_version  <br />";
		
	// 首先检查php环境
	echo "你的网站搭建在 ".PHP_OS." 操作系统的服务器上<br />";
	$software = get_blog_install_software();
	echo "你的网站运行在 $software 服务器上，不同的服务器重写功能会对插件的运行有影响<br />";
	echo "当前的php版本为 ".PHP_VERSION."<br />";
	if(class_exists('ZipArchive')){
		echo "你的PHP支持ZipArchive类，可以正常打包压缩<br />";
	}else{
		echo "PHP不存在ZipArchive类，不能正常备份网站的文件<br />";
	}
	echo '<a href="?phpinfo" target="_blank">点击查看PHPINFO</a><br />';
	

	// 检查是否安装在子目录
	$install_in_subdir = get_blog_install_in_subdir();
	if($install_in_subdir){
		echo "你的WordPress安装在子目录 $install_in_subdir 中，注意重写规则<br />";
	}else{
		echo "你的网站安装在根目录下<br />";
	}

	// 检查重写情况
	$is_rewrite = is_wp_rewrited();
	if($is_rewrite){
		echo "你的网站重写状况如下： $is_rewrite ，请先关闭调试模式，随意阅读一篇文章，看看是否能够被正常访问<br />";
	}else{
		echo "你尚没有修改固定链接形式。<br />";
	}

	// 检查是否开启了多站点功能
	if(is_multisite()){
		echo "你的WordPress开启了群站（多站点），使用如出现问题请及时反馈。<br />";
	}

	// 测试创建文件及其相关
	$file = wp_qiniu_trailing_slash_path(WP_QINIU_TMP_DIR).'wp-qiniu-debug.txt';
	$handle = fopen($file,"w+");
	$words_count = fwrite($handle,'你的服务器支持创建和写入文件');
	if($words_count > 0){
		echo "创建和写入文件成功，你的服务器支持文件创建和写入<br />";
	}
	$file_content = fread($handle,10);
	$read_over = feof($handle);
	if($file_content){
		echo "读取文件成功，你的服务器支持文件读取<br />";
		echo "读取结果为 $read_over ";
	}
	fclose($handle);
	unlink($file);

	// 检查content目录的写入权限
	if(DIRECTORY_SEPARATOR=='/' && @ini_get("safe_mode")==FALSE){
		echo "没有开启安全模式，".(wp_qiniu_is_really_writable(WP_QINIU_TMP_DIR) ? '缓存目录可写' : '缓存目录不可写')."<br />";
	}else{
		echo "开启了安全模式，";
		$file = rtrim(WP_QINIU_TMP_DIR,'/').'/'.md5(mt_rand(1,100).mt_rand(1,100));
		if(($fp = @fopen($file,'w+'))===FALSE){
			echo "缓存目录不可写";
		}else{
			echo "缓存目录可写";
		}
		fclose($fp);
		@chmod($file,'0755');
		@unlink($file);
		echo "<br />";
	}

	// 检查是否存在crossdomain.xml
	$install_root = home_url();
	$domain_root = $install_root;
	if($install_in_subdir){
		$domain_root = str_replace_last($install_in_subdir,'',$install_root);
	}
	if(file_exists(trim($domain_root).'crossdomain.xml')){
		echo "存在crossdomain.xml，<a href='http://".WP_QINIU_SITE_DOMAIN."/crossdomain.xml' target='_blank'>检查一下它是否可以被正常访问</a>，并显示出xml结果<br />";
	}else{
		echo "不存在<a href='http://".WP_QINIU_SITE_DOMAIN."/crossdomain.xml' target='_blank'>crossdomain.xml</a>文件，云存储中的视频可能不能正常播放<br />";
	}


	// 检查云存储连接状态
	$check_link = wp_qiniu_checklink();
	if($check_link['type'] == 'error'){
		echo $check_link['message'];
	}else{
		echo '<p style="color:red;"><b>'.$check_link['message'].'</b></p>';
	}

	// 运行时间
	echo "运行了：".wp_qiniu_get_run_time()."<br />";

	/*
	 * 检查原图保护及图片样式配置
	 */
	$thum_style = get_option('wp_qiniu_thumbnail_style');
	$watermark_style = get_option('wp_qiniu_watermark_style');
	$image_protect = get_option('wp_qiniu_image_protect');
	$split_char = get_option('wp_qiniu_style_split_char');
	
	if(!$image_protect){
		echo '你设置为七牛云存储未开启原图保护，如果七牛云存储已开启原图保护，则必须勾选“存储空间已开启原图保护”选项，并设置缩略图样式、水印样式及样式分隔符。<br/>';
	}else{
		echo '你已勾选了“存储空间已开启原图保护”选项，请确认七牛云存储已开启原图保护。<br/>';
		if(!$split_char)
			echo '如果七牛云存储开启了原图保护，则必须设置插件的样式分隔符，设置的分隔符必须与七牛云存储中的设置一致，否则不能正常访问图片。<br/>';
		else
			echo '插件已设置样式分隔符，请确认此分隔符是否与七牛云存储中的设置一致。<br/>';
		if(!$thum_style)
			echo '如果七牛云存储开启了原图保护，则必须设置插件的缩略图样式，缩略图样式名必须与七牛云存储中设置的一致，否则不能正常获取图片缩略图。<br/>';
		else
			echo '插件已设置缩略图样式，请确认此缩略图样式是否与七牛云存储中的设置一致。<br/>';
		if(!$watermark_style)
			echo '如果七牛云存储开启了原图保护，则必须设置水印样式，水印样式名必须与七牛云存储中设置的一致，否则不能正常访问图片。<br/>';
		else
			echo '插件已设置水印样式，请确认此水印样式是否与七牛云存储中的设置一致。<br/>';
	}

	// 结束调试
	exit;