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
		$audioAttr['autostart'] = $audioAttr['autostart'] ? $audioAttr['autostart'] : 'no';
		$audioAttr['loop'] = $audioAttr['loop'] ? $audioAttr['loop'] : 'no';

		$src = wp_qiniu_get_download_url($audioAttr['key']);
//		$src = urldecode('http://' . WP_QINIU_STORAGE_DOMAIN . '/' .$audioAttr['key']);

		$player = '<script type="text/javascript">AudioPlayer.setup("'.plugins_url("js/simple-audio-player/player.swf",WP_QINIU_PLUGIN_NAME).'",{ width: 290 });</script>';
		$player .= '<p><audio id="'.$player_id.'" controls><source src="'.$src.'" type="'.$audioAttr['type'].'"/></audio></p>';
		$player .= '<script type="text/javascript">AudioPlayer.embed("'.$player_id.'", {soundFile: "'.$src.'",titles:"'.$audioAttr['titles'].'",
			loop:"'.$audioAttr['loop'].'",autostart:"'.$audioAttr['autostart'].'",artists:"'.$audioAttr['artists'].'",
			width:"100%",animation:"yes",encode:"no",initialvolume:"60",remaining:"yes",noinfo:"no",buffer:"5",checkpolicy:"no",rtl:"no",bg:"f3f3f3",text:"333333",
			leftbg:"CCCCCC",lefticon:"333333",volslider:"666666",voltrack:"FFFFFF",rightbg:"B4B4B4",rightbghover:"999999",righticon:"333333",righticonhover:"FFFFFF",
			track:"FFFFFF",loader:"009900",border:"CCCCCC",tracker:"DDDDDD",skip:"666666",pagebg:"",transparentpagebg:"no"});</script>';

		return $player;
	}
	add_shortcode('qiniuaudio','wp_qiniu_audio_shortcode');

	// 注册脚本文件
	function wp_qiniu_add_audioplayer_js($posts) {
		if (empty($posts)) return $posts;
		$shortcode_audioplayer_true = false;
		foreach ($posts as $post) {
			if (stripos($post->post_content, '[qiniuaudio') !== false) {//preg_match('/\[qiniu-audio([^\]]+)?\]/',$wp_query->posts->post_content
				$shortcode_audioplayer_true = true;
				break;
			}
		}
		if ($shortcode_audioplayer_true) {
			wp_enqueue_script('swfobject.js', plugins_url('js/simple-audio-player/swfobject.js', WP_QINIU_PLUGIN_NAME), true);
			wp_enqueue_script('audio-player.js', plugins_url('js/simple-audio-player/audio-player.js', WP_QINIU_PLUGIN_NAME), true);
		}
		return $posts;
	}
	add_filter('the_posts', 'wp_qiniu_add_audioplayer_js');

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

		static $video_id = 1;

		$post_id = get_post() ? get_the_ID() : 0;
		if($video_id == 1){
			echo '<script type="text/javascript" src="'.plugins_url("js/ckplayer/ckplayer.js",WP_QINIU_PLUGIN_NAME).'" charset="utf-8"></script>';
			//echo '<script type="text/javascript" src="'.plugins_url("js/ckplayer/offlights.js",WP_QINIU_PLUGIN_NAME).'"></script>';
		}
		else $video_id ++;

		if(empty($videoAttr['key'])){
			return '<div style="color:red;font-weight:bold;">视频文件错误，必须指定视频文件Key。</div>';
		}
		$videoAttr['width'] = $videoAttr['width'] ? $videoAttr['width'] : '600';
		$videoAttr['height'] = $videoAttr['height'] ? $videoAttr['height'] : '400';

		$src = wp_qiniu_get_download_url($videoAttr['key']);

		$player_id = sprintf( 'videoplayer-%d-%d', $post_id, $video_id);

		$player = '<div id="'.$player_id.'"></div>';
		$player .= '<script type="text/javascript">
			var flashvars={
				f:"'.$src.'",
				c:0,
				b:1,
				i:"http://www.ckplayer.com/static/images/cqdw.jpg"	//初始图片地址
			};
			var params={bgcolor:"#000000",allowFullScreen:true,allowScriptAccess:"always",wmode:"transparent"};
			var video=["'.$src.'->video/mp4"];//'.$videoAttr['type'].'
			CKobject.embed("'.plugins_url("js/ckplayer/ckplayer.swf",WP_QINIU_PLUGIN_NAME).'","'.$player_id.'","ckplayer_'.$player_id.'","100%","100%",false,flashvars,video,params);
		</script>';
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
			'name' => '',
			'type' => ''
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
