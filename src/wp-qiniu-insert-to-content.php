<?php

/*
*
*  这个文件是用来实现从七牛云存储获取附件列表，并让站长可以选择插入到文章中
*
*/

// 在新媒体管理界面添加一个百度网盘的选项
add_filter('media_upload_tabs', 'wp_qiniu_storage_media_tab' );
function wp_qiniu_storage_media_tab($tabs){
	//if(is_plugin_active(WP_QINIU_PLUGIN_NAME))
	//	return null;
	$newtab = array('file_from_qiniu' => '七牛云存储');
    return array_merge($tabs,$newtab);
}

// 这个地方需要增加一个中间介wp_iframe，这样就可以使用wordpress的脚本和样式
add_action('media_upload_file_from_qiniu','media_upload_file_from_qiniu_iframe');
function media_upload_file_from_qiniu_iframe(){
	wp_iframe('wp_qiniu_storage_media_tab_box');
}

// 去除媒体界面的多余脚本
add_action('admin_init','wp_qiniu_storage_media_iframe_remove_actions');
function wp_qiniu_storage_media_iframe_remove_actions(){
	global $hook_suffix;
	if(!$hook_suffix!='media-upload.php'){
		return;
	}
	if(!isset($_GET['tab']) || $_GET['tab'] != 'file_from_qiniu'){
		return;
	}
	remove_all_actions('admin_head');
	remove_all_actions('in_admin_header');
}

// 在上面产生的七牛云存储选项中要显示出七牛云存储内的文件
//add_action('media_upload_file_from_qiniu','wp_qiniu_storage_media_tab_box');
function wp_qiniu_storage_media_tab_box() {

?>
<div style="margin-left: 10px;margin-right: 10px;">
    <?php $chkLink = wp_qiniu_checklink();
    if($chkLink['type']=='success'):?>
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
                <button id="btn-insert" class="button-primary" disabled="disabled">插入</button>
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
    <?php else:
        echo '<div class="notice notice-error" style="background-color:yellow;"><p>';
        echo '<strong>' . esc_html($chkLink['message']) . '</strong></p></div>';
    endif;?>
</div>
<?php
}

