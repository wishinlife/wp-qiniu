<?php

/*
 * audio 的shortcode代码段
 *
 */
	// 创建短代码来打印音乐
	function wp_qiniu_audio_shortcode($atts){
		//global $hook_suffix;

		$audioAttr = shortcode_atts(array(
			'key' => '',
			'titles' => 'Powered by WP-QINIU',
			'artists' => '',
			'autostart' => 'no',
			'loop' => 'no',
			'type' => 'audio/mp3'
		),$atts, 'qiniuaudio');

		static $audio_id = 0;
		$audio_id ++;
		$post_id = get_post() ? get_the_ID() : 0;

		$player_id = sprintf( 'audioplayer-%d-%d', $post_id, $audio_id);

		if(empty($audioAttr['key'])){
			return '<div style="color:red;font-weight:bold;">音频文件错误，必须指定音频文件Key。</div>';
		}
		$audioAttr['titles'] = $audioAttr['titles'] ? $audioAttr['titles'] : 'Powered by WP-QINIU';
		$audioAttr['autostart'] = $audioAttr['autostart'] ? $audioAttr['autostart'] : '0';
		$audioAttr['loop'] = $audioAttr['loop'] ? $audioAttr['loop'] : 'no';

		$src = wp_qiniu_get_download_url($audioAttr['key']);

		$player = '<audio src="'.$src.'" id="'.$player_id.'" title="'.$audioAttr['titles'].'" preload="none" style="width:640px;" autostart="'.$audioAttr['autostart'].'" loop="'.$audioAttr['loop'].'">
		<script type="text/javascript">audiojs.events.ready(function() { var as = audiojs.createAll();});</script>';
		return $player;
	}
	add_shortcode('qiniuaudio','wp_qiniu_audio_shortcode');

	// 注册脚本文件
	function wp_qiniu_add_player_js($posts) {
		if (empty($posts)) return $posts;
		$shortcode_audioplayer_true = false;
		$shortcode_videoplayer_true = false;
		foreach ($posts as $post) {
			if (stripos($post->post_content, '[qiniuaudio') !== false) {
				$shortcode_audioplayer_true = true;
				break;
			}
		}
		if ($shortcode_audioplayer_true) {
			wp_register_script('audio.js', plugins_url('js/audiojs/audio.min.js',WP_QINIU_PLUGIN_NAME), array(), '3.1.2');
			wp_enqueue_script('audio.js');
		}
		foreach ($posts as $post) {
			if (stripos($post->post_content, '[qiniuvideo') !== false) {
				$shortcode_videoplayer_true = true;
				break;
			}
		}
		if ($shortcode_videoplayer_true) {
			wp_register_script('swfobject.js', plugins_url('js/GrindPlayer/swfobject.min.js',WP_QINIU_PLUGIN_NAME), array(), '3.1.2');
			wp_enqueue_script('swfobject.js');
		}
		return $posts;
	}
	add_filter('the_posts', 'wp_qiniu_add_player_js');

/*
 * video 的shortcode代码段
 *
 */
	// 创建短代码来打印视频
	function wp_qiniu_video_shortcode($atts){
		$videoAttr = shortcode_atts(array(
			'key' => '',
			'width' => '600',
			'height' => '400',
			'type' => 'video/mp4'
		),$atts, 'qiniuvideo');

		if(empty($videoAttr['key'])){
			return '<div style="color:red;font-weight:bold;">视频文件错误，必须指定视频文件Key。</div>';
		}
		$videoAttr['width'] = $videoAttr['width'] ? $videoAttr['width'] : '600';
		$videoAttr['height'] = $videoAttr['height'] ? $videoAttr['height'] : '400';

		$src = wp_qiniu_get_download_url($videoAttr['key']);

		static $video_id = 0;
		$video_id ++;
		$post_id = get_post() ? get_the_ID() : 0;

		$player_id = sprintf( 'videoplayer-%d-%d', $post_id, $video_id);

		$player = '<div id="'.$player_id.'"></div>';
		$player .= '<script type="text/javascript">
			var flashvars = {
			    src: "'.$src.'"
			    //src_preroll: "URL TO VIDEO"
			    //src_midroll: "URL TO VIDEO"
				//src_midrollTime: 30 // time in the main media when the ads should be shown
				//src_postroll: "URL TO VIDEO"
			    };
			var params = {allowFullScreen: true, allowScriptAccess: "always", bgcolor: "#000000"};
			var attrs = {name: "'.$player_id.'"};
	        swfobject.embedSWF("'.plugins_url('js/GrindPlayer/GrindPlayer.swf',WP_QINIU_PLUGIN_NAME).'", "'.$player_id.'", "'.$videoAttr['width'].'", "'.$videoAttr['height'].'", "10.2", null, flashvars, params, attrs);</script>';
		return $player;
	}
	add_shortcode('qiniuvideo','wp_qiniu_video_shortcode');

/*
 * file 的shortcode代码段
 *
 */
	// 创建短代码来打印附件文件
	function wp_qiniu_file_shortcode($atts){
		$fileAttr =shortcode_atts(array(
			'key' => '',
			'name' => ''
		),$atts, 'qiniufile');

		// 处理视频文件名，以解决文件路径中存在空格和中文的情况
		if(empty($fileAttr['key'])){
			return '<div style="color:red;font-weight:bold;">附件文件错误，必须指定附件文件Key。</div>';
		}
		$fileAttr['name'] = $fileAttr['name'] ? $fileAttr['name'] : $fileAttr['key'];
		$src = wp_qiniu_get_download_url($fileAttr['key']);

		$html = '<a href="'.$src.'" target="_blank">'.$fileAttr['name'].'</a>';
		return $html;
	}
	add_shortcode('qiniufile','wp_qiniu_file_shortcode');
