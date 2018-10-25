<?php

/*
 * Plugin Name: WP-QINIU
 * Plugin URI: http://www.syncy.cn
 * Description: WP-QINIU主要功能就是把WordPress和七牛云存储连接在一起的插件。主要功能：1、将wordpress的数据库、文件备份到七牛对象云存储，以防止由于过失而丢失了网站数据；2、把七牛对象云存储作为网站的主要存储空间，存放图片、附件，解决网站空间不够用的烦恼；3、可在网站内直接引用七牛云存储上的文件，在写文章时直接点击插入媒体，选择要插入的图片、音频、视频、附件等即可，增强wordpress用户使用七牛云存储的方便性；4、可在wordpress中以目录的形式管理七牛云存储的文件，并可以通过修改文件夹名称来批量修改七牛云存储中文件的Key，方便用户管理文件。
 * Version: 2.0.5
 * Text Domain: wp-qiniu
 * Author:   <a href="http://www.syncy.cn/">WishInLife</a>
 * Author URI: http://www.syncy.cn
 * License:     GPL v2 or later
 */

/*
 *
 * 初始化数据
 *
 */
// 初始化固定值常量
define('WP_QINIU_PLUGIN_NAME', __FILE__);
define('WP_QINIU_PLUGIN_VER', '2.0.5');
require_once( dirname( __FILE__ ) . '/lib/autoload.php' );
use Qiniu\Auth;		// 引入鉴权类
use Qiniu\Storage\UploadManager;	// 引入上传类
use Qiniu\Storage\BucketManager;
// 初始化全局变量
$auth = new Auth(get_option('wp_qiniu_access_key'), get_option('wp_qiniu_secret_key'));// 构建鉴权对象
$bucketMgr = new BucketManager($auth);
$uploadMgr = new UploadManager();// 初始化 UploadManager 对象并进行文件的上传。

// 包含一些必备的函数和类，以提供下面使用
require_once(dirname(__FILE__) . '/wp-qiniu-functions.php');

// 经过判断或函数运算才能进行定义的常量
define('WP_QINIU_ACCESS_KEY', get_option('wp_qiniu_access_key'));
//define('WP_QINIU_SECRET_KEY', get_option('wp_qiniu_secret_key'));
define('WP_QINIU_STORAGE_BUCKET', get_option('wp_qiniu_storage_bucket'));
define('WP_QINIU_BACKUP_BUCKET', get_option('wp_qiniu_backup_bucket'));
define('WP_QINIU_STORAGE_DOMAIN', get_option('wp_qiniu_storage_domain'));
define('WP_QINIU_THUMBNAIL_STYLE', get_option('wp_qiniu_thumbnail_style'));
define('WP_QINIU_WATERMARK_STYLE', get_option('wp_qiniu_watermark_style'));
define('WP_QINIU_STYLE_SPLIT_CHAR', get_option('wp_qiniu_style_split_char'));
define('WP_QINIU_IMAGE_PROTECT', get_option('wp_qiniu_image_protect'));
define('WP_QINIU_ONLY_LOGOUSER', get_option('wp_qiniu_only_logouser'));
define('WP_QINIU_USE_HTTPS', get_option('wp_qiniu_use_https'));
define('WP_QINIU_NOT_CALLBACK', true);  // 不让七牛回调服务器，由客户端提交上传成功的文件信息

define('WP_QINIU_SITE_DOMAIN', $_SERVER['HTTP_HOST']);
//define('WP_QINIU_IS_WIN', strpos(PHP_OS,'WIN')!==false);
define('WP_QINIU_TMP_DIR', wp_qiniu_get_real_path(ABSPATH.'wp_qiniu_tmp'));// WP_QINIU暂时性存储目录
define('WP_QINIU_IS_WRITABLE', wp_qiniu_is_really_writable(WP_QINIU_TMP_DIR));
$wp_qiniu_notices = array();

// 当你发现自己错过了很多定时任务时，可以帮助你执行没有执行完的定时任务
//if(is_admin() && !defined('ALTERNATE_WP_CRON'))
//	define('ALTERNATE_WP_CRON',true);

/*
 *
 * 引入功能文件
 *
 */

//开启调试log输出
define('WP_QINIU_DEBUG', false);
// 开启调试模式
//include(dirname(__FILE__).'/wp-qiniu-debug.php');

// 下面是备份功能文件
require(dirname(__FILE__) . '/wp-qiniu-backup.php');
// 下面是存储功能文件
require(dirname(__FILE__).'/wp-qiniu-file-manage.php');
require(dirname(__FILE__).'/wp-qiniu-shortcodes.php');
require(dirname(__FILE__).'/wp-qiniu-insert-to-content.php');
//	注册ajax
require(dirname(__FILE__).'/wp-qiniu-ajax.php');

/*
 *
 * 初始化设置
 *
 */

// 提高执行时间
add_filter('http_request_timeout','wp_qiniu__filter_timeout_time');
function wp_qiniu__filter_timeout_time($time){
	return 25;
}

// 添加菜单
add_action('admin_menu','wp_qiniu_menu');
function wp_qiniu_menu(){
    global $wp_qiniu_page, $wp_qiniu_page_storage;
	$wp_qiniu_page = add_options_page('WordPress连接七牛云存储','WP-QINIU','edit_theme_options',WP_QINIU_PLUGIN_NAME,'wp_qiniu_pannel');
	$wp_qiniu_page_storage = add_submenu_page('upload.php', 'WordPress连接七牛云存储', '七牛云存储', 'edit_theme_options', 'wp_qiniu_storage', 'wp_qiniu_storage_file_manage');
	add_action('load-'.$wp_qiniu_page, 'wp_qiniu_storage_add_help_page');
	add_action('load-'.$wp_qiniu_page_storage, 'wp_qiniu_storage_add_help_page_storage');

	/*add_menu_page('七牛云存储', 'WP-QINIU', 'manage_options', 'wp-qiniu/wp-qiniu.php', null);
	add_submenu_page('wp-qiniu/wp-qiniu.php', "七牛设置", "设置", 'manage_options', 'wp-qiniu/wp-qiniu.php','wp_qiniu_pannel');
	add_submenu_page('wp-qiniu/wp-qiniu.php', "文件管理", "文件管理", 'publish_pages', 'wp-qiniu/wp-qiniu-file-manage.php', 'wp_qiniu_storage_file_manage');*/


}

// 插件启用时执行表的创建
register_activation_hook(WP_QINIU_PLUGIN_NAME,'wp_qiniu_activation');
function wp_qiniu_activation(){
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix.'wp_qiniu_files';
	if($wpdb->get_var("show tables like `$table_name`")!=$table_name){
		$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
				`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				`pid` BIGINT(20) UNSIGNED NOT NULL,
				`isdir` TINYINT(1) NOT NULL,
				`fname` VARCHAR(128) NOT NULL,
				`fsize` BIGINT(20) NOT NULL DEFAULT '0',
				`ctime` DATETIME NOT NULL,
				`width` SMALLINT(6) NOT NULL DEFAULT '0',
				`height` SMALLINT(6) NOT NULL DEFAULT '0',
				`mimeType` VARCHAR(30) NOT NULL DEFAULT '',
				`flag` TINYINT(1) DEFAULT '0',
				 PRIMARY KEY (`id`),
				 UNIQUE KEY `unique_wp_qiniu` (`pid`,`fname`) USING BTREE,
				 KEY `index_wp_qiniu_pid` (`pid`),
				 KEY `index_wp_qiniu_maintain` (`isdir`,`flag`)
				) $charset_collate;";
		require_once(ABSPATH.'wp-admin/upgrade-functions.php');
		dbDelta($sql);
	}
}

// 停用插件的时候停止定时任务
register_deactivation_hook(WP_QINIU_PLUGIN_NAME,'wp_qiniu_deactivation');
function wp_qiniu_deactivation(){
	delete_option('wp_qiniu_access_key');
	delete_option('wp_qiniu_secret_key'); 
	delete_option('wp_qiniu_storage_bucket');
	delete_option('wp_qiniu_backup_bucket');
	delete_option('wp_qiniu_storage_domain');
	delete_option('wp_qiniu_thumbnail_style');
	delete_option('wp_qiniu_watermark_style');
	delete_option('wp_qiniu_style_split_char');
	delete_option('wp_qiniu_image_protect');
    delete_option('wp_qiniu_only_logouser');
    delete_option('wp_qiniu_use_https');

	delete_option('wp_qiniu_backup_run_rate');
	delete_option('wp_qiniu_backup_run_time');
	delete_option('wp_qiniu_backup_local_paths');
	delete_option('wp_qiniu_actived');

	// 关闭定时任务
	if(wp_next_scheduled('wp_qiniu_backup_corn_task_database'))wp_clear_scheduled_hook('wp_qiniu_backup_corn_task_database');
	if(wp_next_scheduled('wp_qiniu_backup_corn_task_www'))wp_clear_scheduled_hook('wp_qiniu_backup_corn_task_www');

	// 删除文件信息表
	/*global $wpdb;
	$table_name = $wpdb->prefix.'wp_qiniu_files';
	if($wpdb->get_var("show tables like '$table_name'")!=$table_name){
		$wpdb->query("drop table `$table_name`");
	}*/
}

// 添加提交更新动作
add_action('admin_init','wp_qiniu_action');
function wp_qiniu_action(){
	// 权限控制
    if(!is_admin() || !current_user_can('edit_theme_options')){
		return;
	}
	global $wp_qiniu_notices;

	// 保存设置
	if(!empty($_POST) && isset($_POST['action']) && $_POST['action'] == 'update-qiniu-options' && isset($_POST['page']) && $_POST['page'] == $_GET['page']) {
		check_admin_referer( 'update-qiniu-options' );
		// ACCESS KEY
		$access_key = sanitize_text_field( $_POST['wp_qiniu_access_key'] );
		update_option( 'wp_qiniu_access_key', $access_key );
		// Secret key
		$secret_key = sanitize_text_field( $_POST['wp_qiniu_secret_key'] );
		update_option( 'wp_qiniu_secret_key', $secret_key );
		// 七牛绑定的域名
		$storage_domain = sanitize_text_field( $_POST['wp_qiniu_storage_domain'] );
		update_option( 'wp_qiniu_storage_domain', $storage_domain );
		// 存储空间
		$storage_bucket = sanitize_text_field( $_POST['wp_qiniu_storage_bucket'] );
		update_option( 'wp_qiniu_storage_bucket', $storage_bucket );
		// 备份空间
		$backup_bucket = sanitize_text_field( $_POST['wp_qiniu_backup_bucket'] );
		update_option( 'wp_qiniu_backup_bucket', $backup_bucket );
		// 缩略图样式名
		$thumb_style = sanitize_text_field( $_POST['wp_qiniu_thumbnail_style'] );
		update_option( 'wp_qiniu_thumbnail_style', $thumb_style );
		// 水印样式名
		$watermark_style = sanitize_text_field( $_POST['wp_qiniu_watermark_style'] );
		update_option( 'wp_qiniu_watermark_style', $watermark_style );
		// 图片样式分隔符
		$style_splitchar = sanitize_text_field( $_POST['wp_qiniu_style_split_char'] );
		update_option( 'wp_qiniu_style_split_char', $style_splitchar );
        // 已开启https
        $use_https = isset($_POST['wp_qiniu_use_https']) ? sanitize_text_field( $_POST['wp_qiniu_use_https'] ) : '';
        update_option( 'wp_qiniu_use_https', $use_https );
		// 已开启原图保护
		$image_protect = isset($_POST['wp_qiniu_image_protect']) ? sanitize_text_field( $_POST['wp_qiniu_image_protect'] ) : '';
		update_option( 'wp_qiniu_image_protect', $image_protect );
		// 只有登录用户才可查看资源文件（包括图片、音视频和附件）
		$only_logouser = isset($_POST['wp_qiniu_only_logouser']) ? sanitize_text_field( $_POST['wp_qiniu_only_logouser'] ) : '';
		update_option( 'wp_qiniu_only_logouser', $only_logouser );
        
		$feed_actived = isset($_POST['wp_qiniu_feed_actived']) ? sanitize_text_field( $_POST['wp_qiniu_feed_actived']) : get_option('wp_qiniu_feed_actived');
		update_option( 'wp_qiniu_feed_actived', $feed_actived );
        
		array_push($wp_qiniu_notices, array('type'=>'success', 'message'=> '云存储设置保存完成。'));
	}
	// 保存备份设置
	if(!empty($_POST) && isset($_POST['action']) && $_POST['action'] == 'update-backup' && isset($_POST['page']) && $_POST['page'] == $_GET['page']) {
		check_admin_referer('update-backup');
		if(isset($_POST['wp_qiniu_save_backup'])){
			set_php_setting('timezone');
			// 更新定时日周期
			$run_rate = array('www'=> sanitize_text_field($_POST['wp_qiniu_backup_run_rate']['www']),
				'database' => sanitize_text_field($_POST['wp_qiniu_backup_run_rate']['database']));
			update_option('wp_qiniu_backup_run_rate', $run_rate);
			// 更新定时时间点
			$run_time = sanitize_text_field($_POST['wp_qiniu_backup_run_time']);
			update_option('wp_qiniu_backup_run_time', $run_time);
			// 要备份的目录列表
			$local_paths = sanitize_text_field($_POST['wp_qiniu_backup_local_paths']);
			if (!empty($local_paths)) {
				$local_paths = wp_qiniu_get_real_path($local_paths);
				$local_paths = array_filter(explode(";", $local_paths));
				$count = count($local_paths);
				for($i = 0;$i < $count; $i++)
                {
                    for($j = 0; $j < $count; $j++)
                    {
                        if(!isset($local_paths[$j]))
                            continue;
                        if($i!==$j && strpos(wp_qiniu_trailing_slash_path($local_paths[$i]), wp_qiniu_trailing_slash_path($local_paths[$j])) === 0) {
	                        unset( $local_paths[ $i ] );
	                        break;
                        }
                    }
                }
				update_option('wp_qiniu_backup_local_paths', $local_paths);
			} else {
				delete_option('wp_qiniu_backup_local_paths');
				$local_paths = array(ABSPATH);
			}

			// 设置定时任务
			if(wp_next_scheduled('wp_qiniu_backup_corn_task_database'))
				wp_clear_scheduled_hook('wp_qiniu_backup_corn_task_database');
			if(wp_next_scheduled('wp_qiniu_backup_corn_task_www'))
				wp_clear_scheduled_hook('wp_qiniu_backup_corn_task_www');

			if(date('Y-m-d '.$run_time.':00') < date('Y-m-d H:i:s')){
				$run_time = date('Y-m-d '.$run_time.':00',strtotime('+1 day'));
			}else{
				$run_time = date('Y-m-d '.$run_time.':00');
			}
			$run_time = strtotime($run_time);

			foreach($run_rate as $task => $date){
				if($date != 'never'){
					wp_schedule_event($run_time,$date,'wp_qiniu_backup_corn_task_'.$task);
				}
			}
			array_push($wp_qiniu_notices, array('type'=>'success', 'message'=> '备份设置保存完成。'));
			//if(!wp_next_scheduled('wp_qiniu_backup_corn_task_database')) delete_option('wp_qiniu_backup_database_future');
		}
		// 立即备份
		elseif (isset($_POST['wp_qiniu_backup_now'])) {
			$backRst = true;
			set_php_setting('limit');
			set_php_setting('timezone');
			$zip_dir = wp_qiniu_trailing_slash_path(WP_QINIU_TMP_DIR);
			// 备份数据库
			$file_content = "\xEF\xBB\xBF".get_database_backup_all_sql();
			$file_key = WP_QINIU_SITE_DOMAIN.'/database_'.date('Ymd_His').'.sql';
			list($ret,$err) = wp_qiniu_upload($file_content, $file_key);
			if($err != null){
				array_push($wp_qiniu_notices, array('type'=>'error', 'message'=> '数据库备份失败：'.$err->message()));
				$backRst &= false;
			}

			// 备份网站内的所有文件
			$local_paths = get_option('wp_qiniu_backup_local_paths');
			if(!$local_paths) $local_paths = array(ABSPATH);
			if(is_array($local_paths) && WP_QINIU_IS_WRITABLE){
				$file_name = 'www_'.date('Ymd_His').'.zip';
				$www_file = zip_files_in_dirs($local_paths, $zip_dir.$file_name, ABSPATH);
				if($www_file){
					try{
						list($ret,$err) = wp_qiniu_uploadFile($www_file, WP_QINIU_SITE_DOMAIN.'/'.$file_name);
						if($err !== null) {
							array_push($wp_qiniu_notices, array('type'=>'error', 'message'=> '网站文件备份失败：'.$err->message()));
							$backRst &= false;
						}
					} catch (Exception $ex) {
						array_push($wp_qiniu_notices, array('type'=>'error', 'message'=> '网站文件备份失败：'.$ex->getMessage()));
					}
					$del = @unlink($www_file);
					if(!$del)
						array_push($wp_qiniu_notices, array('type'=>'error', 'message'=> '备份临时文件删除失败，请确认备份临时目录权限！'));
				}
			}
			if($backRst)
				array_push($wp_qiniu_notices, array('type'=>'success', 'message'=> '数据库及网站文件备份成功。'));
			//wp_redirect(wp_qiniu_current_request_url(false).'?page='.$_GET['page'].'&time='.time().'#wp-qiniu-backup-area');
			//exit;
		}
	}



}
// 帮助tab页
function wp_qiniu_storage_add_help_page() {
	global $wp_qiniu_page;
	$screen = get_current_screen();
	if ( $screen->id != $wp_qiniu_page )
        return;
	$screen->add_help_tab( array(
		'id'		=> WP_QINIU_PLUGIN_NAME,
		'title'		=> 'WP_QINIU说明',
		'content'	=> '<p>WP_QINIU能做：<ol>
					<li>可将WordPress数据库及指定目录中的文件按规定的时间周期备份到七牛云存储；</li>
					<li>能把七牛云存储作为网站的存储空间，存放网站图片、视频、音乐及文件附件；</li>
					<li>能直接调用七牛云存储中的文件资源，在你的网站中显示；</li>
					<li>可直接向七牛云存储上传文件资源，并支持断点续传（但只能单任务单线程）；</li>
					<li>可直接管理七牛云存储中的文件，并实现按照文件目录形式管理，增强了七牛云存储文件管理功能。</li>
				</ol></p>'
	) );
}

// 选项和菜单
function wp_qiniu_pannel(){
	set_php_setting('timezone');
	$run_rate_arr = get_option('wp_qiniu_backup_run_rate');
	$run_time = get_option('wp_qiniu_backup_run_time');
	$timestamp_database = wp_next_scheduled('wp_qiniu_backup_corn_task_database');
	$timestamp_database = ($timestamp_database ? date('Y-m-d H:i',$timestamp_database) : false);
	$timestamp_www = wp_next_scheduled('wp_qiniu_backup_corn_task_www');
	$timestamp_www = ($timestamp_www ? date('Y-m-d H:i',$timestamp_www) : false);
	$local_paths = get_option('wp_qiniu_backup_local_paths');
	$local_paths = (is_array($local_paths) ? implode(";",$local_paths) : '');
	$backup_rate = wp_qiniu_more_reccurences_for_backup_array();
	$is_turned_on = ($timestamp_database || $timestamp_www);

	global $wp_qiniu_notices;
	array_push($wp_qiniu_notices, wp_qiniu_checklink());

?>
<style>
.tishi{font-size:0.8em;color:#999}
.input-error{
	/*border: 1px solid #ff0000 !important;*/
	background-color: rgba(255, 255, 0, 0.3) !important;
	box-shadow: 0px 0px 1px 1px rgb(255, 0, 0) !important;
}
</style>
<div class="wrap" id="wp2pcs-admin-dashbord">
	<h2>WP_QINIU WordPress连接到七牛云存储</h2>
	<?php
		foreach($wp_qiniu_notices as $notice) {
			if($notice['type']=='error')
				echo '<div class="notice notice-error is-dismissible" style="background-color:yellow;"><p>';
			else
				echo '<div class="notice notice-success is-dismissible" style="background-color:#e0ffff;"><p>';
			echo '<strong>' . esc_html($notice['message']) . '</strong></p></div>';
		}
		$wp_qiniu_notices = array();
	?>
    <div class="metabox-holder">
		<div class="postbox">
			<form method="post" autocomplete="off" id="wp-qiniu-options">
				<h3 style="padding-left: 10px;">WP_QINIU 设置</h3>
				<div class="inside" style="padding-left:30px;">
					<p>Access Key：<input type="text" name="wp_qiniu_access_key" title="" class="regular-text" value="<?php echo get_option('wp_qiniu_access_key'); ?>"/></p>
					<p>Secret Key：<input type="text" name="wp_qiniu_secret_key" title="" class="regular-text" value="<?php echo get_option('wp_qiniu_secret_key'); ?>"></p>
					<p>数据存储访问域名：<input type="text" name="wp_qiniu_storage_domain" title="" style="width:100px;" value="<?php echo get_option('wp_qiniu_storage_domain'); ?>" />(七牛云存储中数据存储空间绑定的域名。)</p>
					<p>数据存储空间名：<input type="text" name="wp_qiniu_storage_bucket" title="" style="width:100px;" value="<?php echo get_option('wp_qiniu_storage_bucket'); ?>" />(用于存储图片、视频、附件等文件，必须是公开存储空间。)</p>
					<p>备份存储空间名：<input type="text" name="wp_qiniu_backup_bucket" title="" style="width:100px;" value="<?php echo get_option('wp_qiniu_backup_bucket'); ?>" />(用于存储网站及数据库等备份文件，一般是私有存储空间。)</p>
					<p>缩略图片样式名：<input type="text" name="wp_qiniu_thumbnail_style" title="" style="width:100px;" value="<?php echo get_option('wp_qiniu_thumbnail_style'); ?>" /></p>
					<p>水印图片样式名：<input type="text" name="wp_qiniu_watermark_style" title="" style="width:100px;" value="<?php echo get_option('wp_qiniu_watermark_style'); ?>" /></p>
					<p>图片样式分隔符：<input type="text" name="wp_qiniu_style_split_char" title="" style="width:100px;" value="<?php if(get_option('wp_qiniu_style_split_char')){ echo get_option('wp_qiniu_style_split_char');}else{ echo '-';} ?>" /></p>
					<p>存储空间已开启 HTTPS：<input type="checkbox" name="wp_qiniu_use_https" value="1" title="" <?php checked('1', get_option('wp_qiniu_use_https')); ?>/></p>
					<p>存储空间已开启原图保护：<input type="checkbox" name="wp_qiniu_image_protect" value="1" title="" <?php checked('1', get_option('wp_qiniu_image_protect')); ?>/></p>
					<p>登录用户才可查看音视频或下载文件：<input type="checkbox" name="wp_qiniu_only_logouser" value="1" title="" <?php checked('1', get_option('wp_qiniu_only_logouser')); ?>/></p>
                    <?php if(!get_option('wp_qiniu_feed_actived')): ?>
                    <p>向作者发送您的邮箱和站点域名：<input type="checkbox" name="wp_qiniu_feed_actived" value="1" title="" <?php checked('1', get_option('wp_qiniu_feed_actived')); ?>/><br/>（邮箱地址及站点域名仅用于记录本插件激活用户数，更多用户的使用是作者继续维护、完善本插件的动力，我们将严格遵循用户隐私保护条款。）</p>
					<?php endif; ?>
                    <p>
						<input type="submit" name="wp_qiniu_save_option" value="保存更改" class="button-primary"/>&nbsp;&nbsp;&nbsp;&nbsp;
						<input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>" />
						<input type="hidden" name="action" value="update-qiniu-options" />
						<?php wp_nonce_field('update-qiniu-options'); ?>
					</p>
				</div>
			</form>
		</div>
		<div class="postbox">
			<form method="post" autocomplete="off">
				<h3 style="padding-left: 10px;">备份设置 <a href="javascript:void(0)" class="tishi-btn">+</a></h3>
				<div class="inside" style="padding-left:30px;">
					<?php if($is_turned_on): ?>
						<p>下一次自动备份时间：
							<?php echo ($timestamp_database ? '数据库：'.esc_html($timestamp_database) : ''); ?>
							<?php echo ($timestamp_www ? '&nbsp;&nbsp;&nbsp;&nbsp;网站：'.esc_html($timestamp_www) : ''); ?>
						</p>
					<?php endif; ?>
					<p id="wp-qiniu-backup-run-area">定时备份：
						数据库<select name="wp_qiniu_backup_run_rate[database]" title=""><?php $run_rate = $run_rate_arr['database']; ?>
							<?php foreach($backup_rate as $rate => $info) : ?>
								<option value="<?php echo esc_attr($rate); ?>" <?php selected($run_rate,$rate); ?>><?php echo esc_html($info['display']); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if(WP_QINIU_IS_WRITABLE) : ?>
							网站<select name="wp_qiniu_backup_run_rate[www]" title=""><?php $run_rate = $run_rate_arr['www']; ?>
									<?php foreach($backup_rate as $rate => $info) : ?>
										<option value="<?php echo esc_attr($rate); ?>" <?php selected($run_rate,$rate); ?>><?php echo esc_html($info['display']); ?></option>
									<?php endforeach; ?>
								</select>
						<?php endif; ?>
						时间：<select name="wp_qiniu_backup_run_time" title="">
							<option <?php selected($run_time,'00:00'); ?>>00:00</option>
							<option <?php selected($run_time,'01:00'); ?>>01:00</option>
							<option <?php selected($run_time,'02:00'); ?>>02:00</option>
							<option <?php selected($run_time,'03:00'); ?>>03:00</option>
							<option <?php selected($run_time,'04:00'); ?>>04:00</option>
							<option <?php selected($run_time,'05:00'); ?>>05:00</option>
							<option <?php selected($run_time,'06:00'); ?>>06:00</option>
						</select>
					</p>
					<?php if(WP_QINIU_IS_WRITABLE) : ?>
						<p class="<?php if(!$local_paths)echo 'tishi hidden'; ?>">
							备份特定的文件或目录：（请阅读下方说明，根据实际路径输入要备份的目录。）<br />
							<textarea name="wp_qiniu_backup_local_paths" class="large-text code" title="" style="height:90px;"><?php echo esc_textarea($local_paths); ?></textarea>
						</p>
						<p class="tishi hidden">多个文件或目录请用分号（;）分隔，当前年月日分别用{year}{month}{day}代替，不能有空格，必须为网站目录绝对路径。<br/>
							<b>注意，上级目录将包含下级目录，如设定项中存在包含关系，系统将自动删除子级目录或文件，只保留父级目录配置项。</b><br/>
							如果填写了目录或文件列表，则只备份填写的目录或文件；不填写，则备份整个网站根目录下的所有文件。</p>
					<?php endif; ?>
					<?php if(!file_exists(WP_QINIU_TMP_DIR)) : ?>
						<p style="color:red">请先手动在你的网站根目录下创建wp_qiniu_tmp目录，并赋予可写权限！</p>
					<?php elseif(!WP_QINIU_IS_WRITABLE) : ?>
						<p style="color:red">当前环境下<?php echo esc_html(WP_QINIU_TMP_DIR); ?>目录没有可写权限，不能备份网站，请赋予这个目录可写权限！</p>
					<?php endif; ?>
					<div class="inside tishi hidden" style="margin:0;padding:0px 10px;">
						<?php if(WP_QINIU_IS_WRITABLE) : ?>
						<p class="tishi hidden" style="color:red;font-weight:bold;">注意：由于备份时需要创建压缩文件，并把压缩文件上传到七牛云存储，因此需要你的网站空间有可写权限和足够的剩余空间，还会消耗你的网站流量，因此请你一定要注意主机的空间及流量情况，以免造成空间塞满或流量耗尽等问题。</p>
						<p class="tishi hidden">因受主机执行时间及网络限制，备份可能会出现失败的情况，请合理使用。<p>
							<?php endif; ?>
					</div>
					<p>
						<input type="submit" name="wp_qiniu_save_backup" value="保存备份设置" class="button-primary"/>&nbsp;&nbsp;&nbsp;&nbsp;
						<input type="submit" name="wp_qiniu_backup_now" value="立即备份" class="button-primary" onclick="if(confirm('立即备份会备份整站数据库和文件或所填写的目录或文件列表，而且现在备份会花费大量的服务器资源，建议在深夜的时候进行！点击“确定”现在备份，点击“取消”则不备份') == false)return false;" />
						<input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>" />
						<input type="hidden" name="action" value="update-backup" />
						<?php wp_nonce_field('update-backup'); ?>
					</p>
				</div>
			</form>
		</div>

		<div class="postbox">
			<div class="inside" style="display:table;padding-bottom: 0;">
				<div style="width:350px;height:100%;border-right:1px solid #CCC;float:left;">
					<p>作者官方网站 <a href="http://www.syncy.cn" target="_blank">http://www.syncy.cn</a></p>
					<p>向作者捐赠：<a href="https://shenghuo.alipay.com/send/payment/fill.htm" target="_blank">支付宝</a> 收款人：<span style="color:#ff0000">wishinlife@gmail.com</span></p>
				</div>
				<div id="wp-qiniu-new-donor" style="height:100%;float:left;">
				</div>
			</div>
		</div>
	</div>
</div>
<script type="text/javascript">
jQuery(function($){
	// 展开按钮
	$('a.tishi-btn').attr('title','点击了解该功能的具体用途').css('text-decoration','none').toggle(function(){
		$(this).parent().parent().find('.tishi').show();
		$(this).text('-');
	},function(){
		$(this).parent().parent().find('.tishi').hide();
		$(this).text('+');
	});
	$(document).ready(function(){
		$.ajax({
			type: "get",
			//timeout: 3000,
			url: "http://www.syncy.cn/newdonor",
			dataType: "jsonp",
			jsonp: "jsonpcallback",
			jsonpCallback: "success_jsonpCallback",
			success:function(data){
				var newdonor = '<p style="padding-bottom:0px;margin-bottom:0px;margin-top:5px;">最新捐赠者：</p><p style="padding-left: 30px; padding-top:0px;margin-top:5px;">';
				for(var i = 0; i < data['donor'].length; i++){
					newdonor = newdonor + data['donor'][i] + '<br/>';
				}
				newdonor = newdonor + '<a style="color:#0000ff;" href="http://www.syncy.cn/index.php/donate" target="_blank">......更多...</a></p>';
				$('#wp-qiniu-new-donor').html(newdonor);
				$('#wp-qiniu-new-donor').css({"margin":"0px","padding":"10px 5px 0px 10px","font-size":"9pt","color":"#993300"});
			}
		});
		$("form#wp-qiniu-options").submit(function(e){
			var reterval = true;
			var message = '';
			$('input.input-error').removeClass('input-error');
			if($("input[name='wp_qiniu_access_key']").val().trim() == ''){
				message += 'Access Key不能为空！\n';
				$("input[name='wp_qiniu_access_key']").addClass('input-error')[0].focus();
				reterval &= false;
			}
			if($("input[name='wp_qiniu_secret_key']").val().trim() == ''){
				message += 'Secret Key不能为空！\n';
				$("input[name='wp_qiniu_secret_key']").addClass('input-error')[0].focus();
				reterval &= false;
			}
			if($("input[name='wp_qiniu_storage_domain']").val().trim() == ''){
				message += '数据存储访问域名不能为空！\n';
				$("input[name='wp_qiniu_storage_domain']").addClass('input-error')[0].focus();
				reterval &= false;
			}
			if($("input[name='wp_qiniu_storage_bucket']").val().trim() == ''){
				message += '数据存储空间名不能为空！\n';
				$("input[name='wp_qiniu_storage_bucket']").addClass('input-error')[0].focus();
				reterval &= false;
			}
			if($("input[name='wp_qiniu_backup_bucket']").val().trim() == ''){
				message += '备份存储空间名不能为空！\n';
				$("input[name='wp_qiniu_backup_bucket']").addClass('input-error')[0].focus();
				reterval &= false;
			}
			if($("input[name='wp_qiniu_thumbnail_style']").val().trim() == ''){
				message += '缩略图片样式名不能为空！\n';
				$("input[name='wp_qiniu_thumbnail_style']").addClass('input-error')[0].focus();
				reterval &= false;
			}
			if($("input[name='wp_qiniu_style_split_char']").val().trim() == ''){
				message += '图片样式分隔符不能为空！\n';
				$("input[name='wp_qiniu_style_split_char']").addClass('input-error')[0].focus();
				reterval &= false;
			}
			if($("input[name='wp_qiniu_image_protect']").attr("checked") && $("input[name='wp_qiniu_watermark_style']").val().trim() == ''){
				message += '如果开启了原图保护，水印图片样式名不能为空！\n';
				$("input[name='wp_qiniu_watermark_style']").addClass('input-error')[0].focus();
				reterval &= false;
			}
			if(!reterval)
				alert(message);
			return !!reterval;
		});
	});
});
</script>
<?php
}
