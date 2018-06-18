<?php

/*
 * audio 的shortcode代码段
 *
 */
	// 创建短代码来打印音乐
	function wp_qiniu_audio_shortcode($atts){
        //global $hook_suffix;
        if(WP_QINIU_ONLY_LOGOUSER && !is_user_logged_in() )
        {
            return '<div style="color:red;font-weight:bold;">请先<a href="./wp-login.php">登录</a></div>';
        }
        else
        {
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

            $player = '<audio id="'.$player_id.'" title="'.$audioAttr['titles'].'" preload="none" controls="controls" style="max-width:100%;">
                <source src="'.$src.'" type="'.$audioAttr['type'].'" />
                    <object type="application/x-shockwave-flash" data="'.plugins_url('js/mediaelement/mediaelement-flash-audio.swf',WP_QINIU_PLUGIN_NAME).'">
                        <param name="movie" value="'.plugins_url('js/mediaelement/mediaelement-flash-audio.swf',WP_QINIU_PLUGIN_NAME).'" /> 
                        <param name="flashvars" value="controls=true&file='.$src.'" /> 	//	&poster=myvideo.jpg
                        <!--img src="myvideo.jpg" width="320" height="240" title="No video playback capabilities" /-->
                    </object>
                </audio>
                <script type="text/javascript">mejs.i18n.language("zh-CN");
                $(document).ready(function(){$(\'#'.$player_id.'\').mediaelementplayer({stretching: "auto",pluginPath: "'.plugins_url('js/mediaelement', WP_QINIU_PLUGIN_NAME).'/", shimScriptAccess: "sameDomain"});});</script>';
            return $player;
        }
	}
	add_shortcode('qiniuaudio','wp_qiniu_audio_shortcode');

	// 注册脚本文件
	function wp_qiniu_add_audioplayer_js($posts) {
		if (empty($posts)) return $posts;
		$shortcode_audioplayer = false;
		$shortcode_videoplayer = false;
		foreach ($posts as $post) {
			if (stripos($post->post_content, '[qiniuaudio') !== false) {
				$shortcode_audioplayer = true;
				break;
			}
		}
		foreach ($posts as $post) {
			if (stripos($post->post_content, '[qiniuvideo') !== false) {
				$shortcode_videoplayer = true;
				break;
			}
		}
		if ($shortcode_audioplayer || $shortcode_videoplayer) {
			wp_register_script( 'mediaelementplayer.js', plugins_url( 'js/mediaelement/mediaelement-and-player.min.js', WP_QINIU_PLUGIN_NAME ), array( 'jquery' ), '4.0.2' );
			wp_enqueue_script( 'mediaelementplayer.js' );
			wp_register_script( 'mediaelement.zh-cn', plugins_url( 'js/mediaelement/lang/zh-cn.js', WP_QINIU_PLUGIN_NAME ), array(), '4.0.2' );
			wp_enqueue_script( 'mediaelement.zh-cn' );
			wp_register_style('mediaelementplayer.css', plugins_url('js/mediaelement/mediaelementplayer.min.css', WP_QINIU_PLUGIN_NAME), array(), '4.0.2');
			wp_enqueue_style('mediaelementplayer.css');
		}
		if($shortcode_videoplayer) {
			//wp_register_script('mediaelement-ads.js', plugins_url('js/mediaelement/plugins/ads.min.js',WP_QINIU_PLUGIN_NAME), array('mediaelementplayer.js'), '2.0.0');
			//wp_enqueue_script('mediaelement-ads.js');
			//wp_register_style('mediaelement-ads.css', plugins_url('js/mediaelement/plugins/ads.min.css', WP_QINIU_PLUGIN_NAME), array(), '2.0.0');
			//wp_enqueue_style('mediaelement-ads.css');
			//wp_register_script('mediaelement-ads-vast.js', plugins_url('js/mediaelement/plugins/ads-vast.min.js',WP_QINIU_PLUGIN_NAME), array('mediaelementplayer.js','mediaelement-ads.js'), '2.0.0');
			//wp_enqueue_script('mediaelement-ads-vast.js');

			//wp_register_script('mediaelement-context-menu.js', plugins_url('js/mediaelement/plugins/context-menu.min.js',WP_QINIU_PLUGIN_NAME), array('mediaelementplayer.js'), '2.0.0');
			//wp_enqueue_script('mediaelement-context-menu.js');
			//wp_register_style('mediaelement-context-menu.css', plugins_url('js/mediaelement/plugins/context-menu.min.css', WP_QINIU_PLUGIN_NAME), array(), '2.0.0');
			//wp_enqueue_style('mediaelement-context-menu.css');
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
        if(WP_QINIU_ONLY_LOGOUSER && !is_user_logged_in() )
        {
            return '<div style="color:red;font-weight:bold;">请先<a href="./wp-login.php">登录</a></div>';
        }
        else
        {
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

            $player = '<video id="'.$player_id.'"  width="'.$videoAttr['width'].'" height="'.$videoAttr['height'].'" preload="none" controls="controls">
                    <source  src="'.$src.'" type="'.$videoAttr['type'].'" />
                    <object width="'.$videoAttr['width'].'" height="'.$videoAttr['height'].'" type="application/x-shockwave-flash" data="'.plugins_url('js/mediaelement/mediaelement-flash-video.swf',WP_QINIU_PLUGIN_NAME).'">
                        <param name="movie" value="'.plugins_url('js/mediaelement/mediaelement-flash-video.swf',WP_QINIU_PLUGIN_NAME).'" /> 
                        <param name="flashvars" value="controls=true&file='.$src.'" /> 	//	&poster=myvideo.jpg
                        <!--img src="myvideo.jpg" width="320" height="240" title="No video playback capabilities" /-->
                    </object>
                </video><script type="text/javascript">mejs.i18n.language("zh-CN");
                $(document).ready(function(){$(\'#'.$player_id.'\').mediaelementplayer({
                    //adsPrerollMediaUrl: [],         //array | [] | URL to a media file
                    //adsPrerollAdUrl: [],            //array | [] | URL for clicking ad 	
                    //adsPrerollAdEnableSkip: "false",//boolean | false | If true, allows user to skip the pre-roll ad
                    //adsPrerollAdSkipSeconds: "10",  //number | -1 | If positive number entered, it will only allow skipping after the time has elasped
                    //indexPreroll: "0",              //number | 0 | Keep track of the index for the preroll ads to be able to show more than one preroll. Used for VAST3.0 Adpods
                    stretching: "auto",pluginPath: "'.plugins_url('js/mediaelement', WP_QINIU_PLUGIN_NAME).'/", shimScriptAccess: "always",
                    success: function(player, node) {
                        $(player).closest(\'.mejs__container\').attr(\'lang\', mejs.i18n.language());
                        $(\'html\').attr(\'lang\', mejs.i18n.language());
                    }
                });});</script>';
            return $player;
        }
	}
	add_shortcode('qiniuvideo','wp_qiniu_video_shortcode');

/*
 * file 的shortcode代码段
 *
 */
	// 创建短代码来打印附件文件
	function wp_qiniu_file_shortcode($atts){
        if(WP_QINIU_ONLY_LOGOUSER && !is_user_logged_in() )
        {
            return '<div style="color:red;font-weight:bold;">请先<a href="http://' . WP_QINIU_SITE_DOMAIN . '/wp-login.php">登录</a></div>';
        }
        else
        {
            $fileAttr =shortcode_atts(array(
                'key' => '',
                'name' => ''
                ),$atts, 'qiniufile');
                    
            if(empty($fileAttr['key'])){
                return '<div style="color:red;font-weight:bold;">附件文件错误，必须指定附件文件Key。</div>';
            }
            
            // 处理视频文件名，以解决文件路径中存在空格和中文的情况
            $fileAttr['name'] = $fileAttr['name'] ? $fileAttr['name'] : $fileAttr['key'];
            $src = wp_qiniu_get_download_url($fileAttr['key']);

            $html = '<a href="'.$src.'" target="_blank">'.$fileAttr['name'].'</a>';
            return $html;
        }
	}
	add_shortcode('qiniufile','wp_qiniu_file_shortcode');
