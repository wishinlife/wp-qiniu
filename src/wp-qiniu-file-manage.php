<?php

/*
*
*  这个文件是用来实现在媒体管理中管理七牛云存储中的文件，可上传、删除文件。
*
*/

function wp_qiniu_storage_add_help_page_storage() {
	global $wp_qiniu_page_storage;
	$screen = get_current_screen();
	if ( $screen->id != $wp_qiniu_page_storage ){
		return;
	}
	$screen->add_help_tab( array(
		'id'		=> 'wp-qiniu-storage',
		'title'		=> '说明',
		'content'	=> '<p>
			<ul>
				<li>点击上传按钮可上传文件至七牛云存储，你上传完文件之后，点击返回按钮在最末尾就可以看到刚上传完成的文件。</li>
				<li>文件名、文件夹名请使用常规的命名方法，文件名中不能包含：<span style="color:red;font-weight: bolder">'.esc_html('`~!#$%&*()+=[]\{}|;\':",/<>?').'</span> 等特殊字符，不要有空格。</li>
				<li>文件名中如含有以上特殊字符，将会被删除，空格将会被“-”替代，因此可能存在文件上传完成后本地文件名与云端文件名不一致的情况。</li>
                <li><span style="color:red;font-weight: bolder">文件及文件夹删除功能为永久性删除，删除后不可恢复，请谨慎使用！</span></li>
			</ul>
		</p>'
	) );
	$screen->add_help_tab( array(
		'id'		=> 'wp-qiniu-orderby',
		'title'		=> '排序',
		'content'	=> '<p><ul><li>选定排序类型后需要点击“刷新”按钮才会按照新的排序规则排序。</li><li>默认排序规则为按照修改时间倒排序。</li></ul></p>'
	) );
	$screen->add_help_tab( array(
		'id'		=> 'wp-qiniu-sync',
		'title'		=> '同步',
		'content'	=> '<p><ul><li>同步功能将会与七牛云存储同步当前目录及其子目录的文件信息，七牛云存储中有的新文件，而Wordpress中没有此文件记录的将会添加到文件列表中；七牛云存储中不存在但在Wordpress中有文件记录的将会删除Wordpress中的文件记录。
                        如果文件较多，将需要较长时间，<span style="color:red;font-weight: bolder">请勿重复点击“同步”按钮</span>。</li></ul></p>'
	) );
}

// 在上面产生的七牛云存储选项中要显示出云存储内的文件
//add_action('media_upload_file_from_pcs','wp_qiniu_storage_media_tab_box');
function wp_qiniu_storage_file_manage() {
	// 文件管理面板
?>
<div class="wrap" id="wp-qiniu-file-dashbord">
	<h2>七牛云存储文件管理</h2>
	<?php $chkLink = wp_qiniu_checklink();
	if($chkLink['type'] =='success'):?>
        <div class="opt-ctl-tabs" id="opt-on-qiniu-tabs">
			<p>当前位置：
				<span id="wp-qiniu-path-navi">
					<a href="javascript:void(0)" data-file-id="0" data-file-path="" class="link-Disabled">HOME</a>
				</span>
				<?php if((is_multisite() && current_user_can('manage_storage')) || (!is_multisite() && current_user_can('edit_theme_options'))): ?>
					<button class="button-primary right" id="show-upload-area">上传到这里</button>
				<?php endif; ?>
			</p>
			<p id="manage-buttons">
				<button id="btn-reload" class="button">刷新</button>
	            <button id="btn-newdir" class="button">新建文件夹</button>
				<button id="btn-rename" class="button" disabled="disabled">重命名</button>
				<button id="btn-clear" class="button" disabled="disabled">取消选择</button>
				<button id="btn-delete" class="button" disabled="disabled">删除</button>
                <button id="btn-sync" class="button" title="与七牛云存储同步当前目录及其子目录的文件信息">同步</button>
				<span style="margin-left:30px;">
					排序：<input type="radio" name="wp-qiniu-file-orderby" title="" value="ctime-desc" checked/>时间倒排
					<input type="radio" name="wp-qiniu-file-orderby" title="" value="ctime-asc"/>时间顺排
					<input type="radio" name="wp-qiniu-file-orderby" title="" value="fname-desc"/>文件名倒排
					<input type="radio" name="wp-qiniu-file-orderby" title="" value="fname-asc"/>文件名顺排

				</span>
			</p>
			<div class="clear"></div>
		</div>
		<div id="files-on-qiniu"></div>
		<!--文件div插入位置-->

		<div style="clear:both;"></div>
		<div id="load-more" class="page-navi" data-pid="0" data-paged="1" data-pagesize="100" data-current-path="">加载更多</div>


		<div id="upload-to-qiniu-area" style="display:none;" class="container">
			<div id="upload-container" style="position: relative;">
				<label id="upload-pickfiles">
					<span></span>
                    <em>将文件拖到这里<br/>或<br/>选择文件</em>
				</label>
			</div>
			<div style="display:none;" id="upload-detail">
				<div class="col-lg-12" id="upload-success" style="display:none;">
	                <div class="alert-success">
	                    队列全部文件处理完毕
	                </div>
	            </div>
	            <div class="col-lg-12 ">
	                <table id="upload-file-detail" class="table table-striped table-hover text-left" style="margin-top: 50px;">
						<thead>
							<tr>
								<th class="col-md-4">文件名</th>
								<th class="col-md-2">文件大小</th>
	                            <th class="col-md-4">详细信息</th>
	                            <th class="col-md-2"><button class="button right" id="clear-upload-info">清除已完成文件列表</button></th>
							</tr>
						</thead>
						<tbody id="fsUploadProgress"></tbody>
					</table>
				</div>
			</div>
	        <div class="clear"></div>
		</div>

		<a href="javascript:void(0)" title="返回顶部">
			<div id="back-to-top-btn" onclick="jQuery('html,body').animate({scrollTop:0},500)">回顶部</div>
		</a>
		<input type="hidden" id="wp-qiniu-storage-domain" value="<?php echo esc_attr('http://'.WP_QINIU_STORAGE_DOMAIN.'/');?>">
		<input type="hidden" id="wp-qiniu-thumbnail-style" value="<?php echo esc_attr(WP_QINIU_THUMBNAIL_STYLE);?>">
		<input type="hidden" id="wp-qiniu-watermark-style" value="<?php echo esc_attr(WP_QINIU_WATERMARK_STYLE);?>">
		<input type="hidden" id="wp-qiniu-style-split-char" value="<?php echo esc_attr(WP_QINIU_STYLE_SPLIT_CHAR);?>">
		<input type="hidden" id="wp-qiniu-plugin-url" value="<?php echo esc_attr(plugins_url('/',WP_QINIU_PLUGIN_NAME));?>">
		<input type="hidden" id="wp-admin-ajax-url" value="<?php echo esc_attr(admin_url('admin-ajax.php'));?>">
		<input type="hidden" id="wp_qiniu_ajax_nonce" value="<?php echo wp_create_nonce('wp_qiniu_ajax_nonce'); ?>"/>
	    <div id="log" style="margin-top: 20px;display: none;">
	        <pre id="qiniu-js-sdk-log" style="border-radius: 4px;padding: 9px;margin: 0 0 10px;border: 1px solid #ccc;background-color: #f5f5f5;word-wrap: break-word;word-break: break-all;line-height: 20px;"></pre>
	    </div>
	<?php else:
		echo '<div class="notice notice-error" style="background-color:yellow;"><p>';
		echo '<strong>' . esc_html($chkLink['message']) . '</strong></p></div>';
	endif;?>
</div>
<?php
}
// 注册样式文件
function wp_qiniu_admin_load_resources() {
	/* //在特定页面或插件加载样式文件  $hook
     * //https://codex.wordpress.org/Plugin_API/Action_Reference/admin_enqueue_scripts
	 */
	global $hook_suffix;
	if ( in_array( $hook_suffix, array(
		//'post-new.php',
		'media_page_wp_qiniu_storage',
        'media-upload.php'//, 'upload.php'
	) ) ) {
		wp_register_style('wp_qiniu_file_manage.css', plugins_url('css/file-manage.min.css', WP_QINIU_PLUGIN_NAME), array(), WP_QINIU_PLUGIN_VER);
		wp_enqueue_style('wp_qiniu_file_manage.css');
		wp_register_style('qiniu-upload.css', plugins_url('css/qiniu-upload.min.css', WP_QINIU_PLUGIN_NAME), array(), WP_QINIU_PLUGIN_VER);
		wp_enqueue_style('qiniu-upload.css');

        wp_register_script('qiniu.js', plugins_url('js/qiniu.min.js',WP_QINIU_PLUGIN_NAME), array('jquery', 'plupload'), '1.0.18');
		wp_enqueue_script('qiniu.js');
		wp_register_script('file-manage.js', plugins_url('js/file-manage.min.js',WP_QINIU_PLUGIN_NAME), array('jquery', 'plupload','qiniu.js'), WP_QINIU_PLUGIN_VER);
		wp_enqueue_script('file-manage.js');
		wp_register_script('qiniu-upload.js', plugins_url('js/qiniu-upload.min.js',WP_QINIU_PLUGIN_NAME), array('jquery', 'plupload','qiniu.js'), WP_QINIU_PLUGIN_VER);
		wp_enqueue_script('qiniu-upload.js');
	}else{
		return;
		//die($hook_suffix);
	}
}
add_action('admin_enqueue_scripts', 'wp_qiniu_admin_load_resources');
