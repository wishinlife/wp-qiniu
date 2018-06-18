WP-QINIU （WordPress连接到七牛云存储）

* Contributors: wishinlife
* Donate link: http://www.syncy.cn/index.php/donate/
* Tags:wp-qiniu, backup, sync, qiniu, object cloud storage, 七牛云存储
* Requires at least: 4.5.0
* Tested up to: 4.9.6
* Stable tag: 1.6.1
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html


## 主要功能

1、将wordpress的数据库、文件备份到七牛对象云存储，以防止由于过失而丢失了网站数据；
2、把七牛对象云存储作为网站的主要存储空间，存放图片、附件，解决网站空间不够用的烦恼；
3、可在网站内直接引用七牛云存储上的文件，在写文章时直接点击插入媒体，选择要插入的图片、音频、视频、附件等即可，增强wordpress用户使用七牛云存储的方便性；
4、可在wordpress中以目录的形式管理七牛云存储的文件，并可以通过修改文件夹名称来批量修改七牛云存储中文件的Key，方便用户管理文件。

七牛云存储官网地址：http://www.qiniu.com

WP-QINIU官方网站：http://www.syncy.cn

## 安装方法

1、把wp-qiniu文件夹上传到/wp-content/plugins/目录下<br />
2、在后台插件列表中激活wp-qiniu<br />
3、在“插件->WP-QINIU”菜单中输入七牛云存储的AK、SK等设置项并保存（设置项需与七牛云存储设置对应）<br />
4、如果在BAE上备份不成功，可修改wordpress根目录下的wp-cron.php，在文件开头增加语句“set_time_limit(0);”看能否正常备份。