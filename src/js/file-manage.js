jQuery(function($){
	// 选择要插入的附件
	$('div.file-on-qiniu').live('click',function(e){
		/*$('div.selected').each(function(){ //禁止多选
			$('.selected').removeClass('selected');
		});*/ 
		//if($('#rename-file').is(":visible"))return;
		$(this).toggleClass('selected');
		var divSelected = $('div.file-on-qiniu.selected');
		if(divSelected.length > 0) {
            $('#btn-delete').attr('disabled', false);
            $('#btn-clear').attr('disabled', false);
        } else {
            $('#btn-delete').attr('disabled', true);
            $('#btn-clear').attr('disabled', true);
        }
        if(divSelected.length == 1)
			$('#btn-rename').attr('disabled', false);
		else
			$('#btn-rename').attr('disabled', true);
		if($('div[data-file-type!="dir"].file-on-qiniu.selected').length > 0)
			$('#btn-insert').attr('disabled', false);
		else
			$('#btn-insert').attr('disabled', true);
	});

	// 点击插入按钮
	$('#btn-insert').live('click', function(){
		var divSelFiles = $('div[data-file-type!="dir"].file-on-qiniu.selected');
		if(divSelFiles.length > 0){
			var root_url = $('#wp-qiniu-storage-domain').val(),
				pkey = $('#load-more').attr('data-current-path'),
				nhtml = '';
			divSelFiles.each(function(){
				var $this = $(this),
					fname = $this.attr('data-file-name'),
					ftype = $this.attr('data-file-type'),
					fmime = $this.attr('data-file-mime'),
					fkey = pkey + fname;
				// 如果被选择的是图片
				if(ftype == 'image'){
                    var watermark = '';
                    if($('#wp-qiniu-watermark-style').val())
                        watermark = $('#wp-qiniu-style-split-char').val() + $('#wp-qiniu-watermark-style').val();
					var img_src = root_url + encodeURI(fkey + watermark);
					nhtml += '<a href="'+img_src+'"><img src="'+img_src+'" alt="'+fname+'" /></a>';
				}
				// 如果被选择的是视频，使用视频播放器
				else if(ftype == 'video'){
					nhtml += '[qiniuvideo key="'+ fkey +'" width="600" height="400" type="'+fmime+'"]';
				}
				// 如果被选择的是音乐，使用音频播放器
				else if(ftype == 'audio'){
					nhtml += '[qiniuaudio key="'+ fkey +'" titles="'+fname+'" artists="" autostart="no" loop="no" type="'+fmime+'"]';
				}
				// 如果是其他文件，就直接给媒体链接
				else{
					nhtml += '[qiniufile key="'+ fkey +'" name="'+fname+'" type="'+fmime+'"]';
				}
			});
			divSelFiles.removeClass('selected');

			// http://stackoverflow.com/questions/13680660/insert-content-to-wordpress-post-editor
			window.parent.send_to_editor(nhtml);
			window.parent.tb_remove();
		}else{
			alert('请选择要插入的文件！');
		}
	});

	// 点击关闭按钮
	$('#btn-close').click(function(){
		window.parent.tb_remove();
	});

	// 清除选择的图片
	$('#btn-clear').click(function(){
		var divSelFiles = $('div.file-on-qiniu.selected');
		divSelFiles.removeClass('selected');
		$('#btn-delete').attr('disabled', true);
		$('#btn-clear').attr('disabled', true);
		$('#btn-insert').attr('disabled', true);
		$('#btn-rename').attr('disabled', true);
    });

	// 点击刷新
	$('#btn-reload').click(function(){
		$('#files-on-qiniu').empty();
		$('#btn-clear').click();
		var divLoadMore = $('#load-more');
		divLoadMore.attr('data-paged',1);
		divLoadMore.attr('data-loading','false');
		divLoadMore.attr("class","page-navi");
		divLoadMore.text('加载更多');
		divLoadMore.click();	// 开始加载文件
	});

	// 点击切换到上传面板
	$('#show-upload-area').toggle(function(e){
			e.preventDefault();
			$('#files-on-qiniu,#load-more,#prev-page,#manage-buttons').hide();
			$('#upload-to-qiniu-area').show();
			$('#wp-qiniu-path-navi').find('a').attr('class', 'link-Disabled');
			$(this).text('返回列表');
		},function(e){
			e.preventDefault();
			$('#upload-to-qiniu-area').hide();
			var divPathNavi = $('#wp-qiniu-path-navi');
			divPathNavi.find('a').attr('class', 'link-Active');
			divPathNavi.find('a:last').attr('class', 'link-Disabled');
			$('#files-on-qiniu,#load-more,#prev-page,#manage-buttons').show();
			$(this).text('上传到这里');
		});

	// 点击加载更多列表
	$('#load-more').live('click', function(e){
		e.preventDefault();
		var $this = $(this),
			loading = $this.attr('data-loading'),
			pid = $this.attr('data-pid'),
			paged = $this.attr('data-paged'),
			pagesize = $this.attr('data-pagesize'),
			workPath = $this.attr('data-current-path'),
			interval = $this.attr('timer-int');
		if(loading=='true') return;
        if(interval != 'undefined')
            clearInterval(interval);
		$.ajax({
			type: "get",
			//timeout: 3000,
			dataType: "json",
			url: $('#wp-admin-ajax-url').val(),
			data: {
				action: "wp_qiniu_list_files", 
				pid : pid, 
				paged: paged, 
				pagesize: pagesize, 
				orderby: $('input[name="wp-qiniu-file-orderby"]:checked').val(),
				nonce: $('#wp_qiniu_ajax_nonce').val()
			},
			beforeSend:function(){
				$this.attr("class","page-navi-disable");
				$this.attr('data-loading','true');
				$this.text('正在加载');
				function fileLoad(){
					var text = $this.text();
					if(text.length < 14 && text.length >= 4)
						$this.text(text + ' .');
					else
						$this.text('正在加载 .');
				}
				interval = setInterval(fileLoad, 1000);
                $this.attr('timer-int',interval);
			},
			success:function(data, textStatus){
				clearInterval(interval);
				if(data['status']!='success'){
					alert(data['status']);
					$this.attr('data-loading','false');
					$this.text('加载失败！点击后重新加载。Error：'+ data['error']);
					$this.attr("class","page-navi");
					return;
				}
				var filelists = data['data'],
					dirUrl = $('#wp-qiniu-storage-domain').val() + encodeURI($this.attr('data-current-path')),
					pluginUrl = $('#wp-qiniu-plugin-url').val(),
					thumbnailStyle = $('#wp-qiniu-style-split-char').val() + $('#wp-qiniu-thumbnail-style').val(),
					ftype = "";
				for(var i=0;i<filelists.length;i++){
					var id = filelists[i]['id'],
						isdir = filelists[i]['isdir'],
						fname = filelists[i]['fname'],
						fsize = filelists[i]['fsize'],
						ctime = filelists[i]['ctime'],
						width = filelists[i]['width'],
						height = filelists[i]['height'],
						fmime = filelists[i]['mimeType'],
						imgArr = '.|jpg|jpeg|png|gif|bmp|',
						videoArr = '.|asf|avi|flv|mkv|mov|mp4|wmv|3gp|3g2|mpeg|ts|rm|rmvb|m3u8|',
						audioArr = '.|ogg|mp3|wma|wav|mp3pro|mid|midi|';
					if(isdir==1)
						ftype = 'dir';
					else
						ftype = fname.substring(fname.lastIndexOf('.') + 1).toLowerCase();
					// 判断是否为图片
					if(imgArr.indexOf('|'+ftype+'|')>0){
						ftype = 'image';
					}
					else if(videoArr.indexOf('|'+ftype+'|')>0){
						ftype = 'video';
					}
					else if(audioArr.indexOf('|'+ftype+'|')>0){ //
						ftype = 'audio';
					}
					else if(isdir==0){
						ftype = 'file';
					}

					var fnode_html = '<div class="file-on-qiniu'+'" data-file-name="'+fname+'" data-file-type="'+ftype+'" data-file-id="'+id+'" data-file-mime="'+fmime+'">';
					fnode_html += '<div class="file-thumbnail">';
					if(ftype == 'image')
						fnode_html += '<img src="'+dirUrl +encodeURI(fname) + thumbnailStyle + '" />';
					else
						fnode_html += '<img src="'+ pluginUrl + 'img/' + ftype + '.png" />';

					fnode_html += '</div>';
					fnode_html += '<div class="file-name"><div class="file-text">';
					fnode_html += fname; 
					fnode_html += '</div></div>';
					fnode_html += '</div>';

					if(workPath != $this.attr('data-current-path'))
						return;
					$('#files-on-qiniu').append(fnode_html);
				}

				$this.attr('data-paged',++paged);
				if(filelists.length < pagesize) {
					$this.attr('data-loading','true');
					$this.text('已全部加载完成');
					$this.attr("class","page-navi-disable");
				}else{
					$this.attr('data-loading','false');
					$this.text('加载更多');
					$this.attr("class","page-navi");
				}
			},
			error: function (XMLHttpRequest, textStatus, errorThrown) {
				clearInterval(interval);
				$this.attr('data-loading','false');
				$this.attr("class","page-navi");
				if(XMLHttpRequest.responseText) {
					var res = JSON.parse(XMLHttpRequest.responseText);
					$this.text('加载失败！点击后重新加载。' + res['error'] + ", " + XMLHttpRequest.status + ": " + errorThrown + ", status: " + textStatus);
				} else
					$this.text('加载失败！点击后重新加载。' + XMLHttpRequest.status + ": " + errorThrown + ", status: " + textStatus);
			},
			complete: function (XMLHttpRequest, textStatus) {
                $this.removeAttr('timer-int');
			}
		});
	});
	//页面加载完成，加载第一页文件列表
	$(document).ready(function(){
		$('#load-more').click();
	});
	// 双击文件夹
	$('div.file-on-qiniu[data-file-type="dir"]').live('dblclick', function(e){
        $('#btn-clear').click();
		var $this = $(this),
			pid = $this.attr('data-file-id'),
			fname = $this.attr('data-file-name'),
			divLoadMore = $('#load-more'),
			fpath = divLoadMore.attr('data-current-path')+fname+'/';
		divLoadMore.attr('data-pid',pid);
		divLoadMore.attr('data-paged',1);
		divLoadMore.attr('data-current-path',fpath);
		$('#files-on-qiniu').empty();
		
		var lhtml = '/<a class="link-Disabled" href="javascript:void(0)" data-file-path="'+fpath+'" data-file-id="'+pid+'">'+fname+'</a>',
			divPathNavi = $('#wp-qiniu-path-navi');

		divPathNavi.find('a.link-Disabled').attr('class', 'link-Active');
		divPathNavi.append(lhtml);

		divLoadMore.text('加载更多');
		divLoadMore.attr("class","page-navi");
		divLoadMore.attr('data-loading','false');
		divLoadMore.click();	// 开始加载文件
	});
	// 点击toolbar中的连接
	$('#wp-qiniu-path-navi').find('a.link-Active').live('click',function(e){
        $('#btn-clear').click();
		var $this = $(this),
			pid = $this.attr('data-file-id'),
			fpath = $this.attr('data-file-path'),
			divLoadMore = $('#load-more'),
			divPathNavi = $('#wp-qiniu-path-navi'),
			phtml = divPathNavi.html();

		divLoadMore.attr('data-pid',pid);
		divLoadMore.attr('data-paged',1);
		divLoadMore.attr('data-current-path',fpath);
		$('#files-on-qiniu').empty();

		var i = phtml.indexOf('</a>', phtml.indexOf('data-file-id="'+pid+'">'));
		var naviHtml = phtml.substring(0,i+4);
		divPathNavi.html(naviHtml);
		divPathNavi.find('a:last').attr('class', 'link-Disabled');

		divLoadMore.text('加载更多');
		divLoadMore.attr("class","page-navi");
		divLoadMore.attr('data-loading','false');
		divLoadMore.click();	// 开始加载文件
	});
	// 新建文件夹
	$('#btn-newdir').click(function(){
		var dirname = prompt("请输入文件夹的名称（请勿使用特殊字符及系统保留字符）：", '新建文件夹');
		if(dirname == null)
			return;
		if(dirname.trim(' ') == '') {
			alert('文件夹名不能为空！');
			return false;
		}
        var $this = $(this),
			dircheck = true;
		var fNode = $('div.file-on-qiniu[data-file-type="dir"]:first');
		while(fNode && fNode.length && fNode.length > 0){
			if(dirname == fNode.attr('data-file-name')){
				alert('已存在相同名称的文件夹！');
				$this.attr('disabled', false);
				dircheck = false;
				break;
			}
			fNode = fNode.next('div.file-on-qiniu[data-file-type="dir"]');
		}

		if(!dircheck)
			return;
		var loadmore = $('#load-more'),
			pid = loadmore.attr('data-pid'),
			workPath = loadmore.attr('data-current-path');

		$.ajax({
			type: "get",
			//timeout: 3000,
			dataType: "json",
			url: $('#wp-admin-ajax-url').val(),
			data: {
				action: "wp_qiniu_create_floder",
				pid: pid,
				dirname: dirname,
				nonce: $('#wp_qiniu_ajax_nonce').val()
			},
			beforeSend: function () {
				$this.attr('disabled', true);
			},
			success: function (data, textStatus) { //textStatus为success
				if(data['status']!='success'){
					alert('文件夹创建失败！Error：'+ data['error']);
					return;
				}

				var id = data['id'],
					isdir = data['isdir'],
					fname = data['fname'],
					ctime = data['ctime'],
					fmime = data['mimeType'],
					ftype = 'dir',
					pluginUrl = $('#wp-qiniu-plugin-url').val();

				var fnode_html = '<div class="file-on-qiniu" data-file-name="'+fname+'" data-file-type="dir" data-file-id="'+id+'" data-file-mime="'+fmime+'">';
				fnode_html += '<div class="file-thumbnail">';
				fnode_html += '<img src="'+ pluginUrl + 'img/' + ftype + '.png" />';
				fnode_html += '</div>';
				fnode_html += '<div class="file-name"><div class="file-text">';
				fnode_html += fname;
				fnode_html += '</div></div>';
				fnode_html += '</div>';

				if(workPath != $('#load-more').attr('data-current-path'))
					return;
				$('#files-on-qiniu').append(fnode_html);
			},
			error: function (XMLHttpRequest, textStatus, errorThrown) {
				if(XMLHttpRequest.responseText) {
					var res = JSON.parse(XMLHttpRequest.responseText);
					alert('文件夹创建失败！' + res['error'] + ", " + XMLHttpRequest.status + ": " + errorThrown + ", status: " + textStatus);
				} else
					alert('文件夹创建失败！' + XMLHttpRequest.status + ": " + errorThrown + ", status: " + textStatus);
			},
			complete: function (XMLHttpRequest, textStatus) {
				$this.attr('disabled', false);
			}
		});
	});
	// 删除文件或文件夹
	$('#btn-delete').click(function(){
		var rst = confirm('你确定要删除选定的所有文件及文件夹吗？文件删除后将不可恢复！如果文件较多将需要较长时间。');
		if(rst == false)
			return;
		var $this = $(this),
			pid = $('#load-more').attr('data-pid'),
			files = new Array(),
			i = 0;
		$('div.file-on-qiniu.selected').each(function(){
			var id = $(this).attr('data-file-id');
			files[i] = id;
			i++;
		});
		$.ajax({
			type: "post",
			//timeout: 3000,
			dataType: "json",
			url: $('#wp-admin-ajax-url').val(),
			data: {
				action: "wp_qiniu_delete_files",
				files: files,
				nonce: $('#wp_qiniu_ajax_nonce').val()
			},
			beforeSend: function (XMLHttpRequest) {
				$this.attr('disabled', true);
                $('#btn-clear').attr('disabled', true);
                $('#btn-insert').attr('disabled', true);
                $('#btn-reload').attr('disabled', true);
                $('#btn-rename').attr('disabled', true);
			},
			success: function (data, textStatus) {
				if(data['status']!='success'){
					alert('删除失败！Error：'+ data['error']);
					$this.attr('disabled', false);
					return;
				}
				var deled = data['data'],
					allDel = true,
					divFiles = $('#files-on-qiniu');
				for(var i=0; i<deled.length; i++){
					if(pid != $('#load-more').attr('data-pid')){
						$this.attr('disabled', false);
						return;
					}
					if(deled[i]['result'])
						divFiles.find('div.file-on-qiniu[data-file-id="'+deled[i]['id']+'"]').remove();
					allDel &= deled[i]['result'];
				}
				if(!allDel) {
                    alert('部分文件未能成功删除，如需继续删除，请刷新后再操作。');
                } else {
                    alert('已成功删除所有选定的文件或文件夹。');
                }
			},
			error: function (XMLHttpRequest, textStatus, errorThrown) {
				if(XMLHttpRequest.responseText) {
					var res = JSON.parse(XMLHttpRequest.responseText);
					alert('删除失败！' + res['error'] + ", " + XMLHttpRequest.status + ": " + errorThrown + ", status: " + textStatus);
				} else
					alert('删除失败！' + XMLHttpRequest.status + ": " + errorThrown + ", status: " + textStatus);
			},
			complete: function (XMLHttpRequest, textStatus) {
				var divSelected = $('div.file-on-qiniu.selected');
				if(divSelected.length > 0) {
					$('#btn-delete').attr('disabled', false);
					$('#btn-clear').attr('disabled', false);
				} else {
					$('#btn-delete').attr('disabled', true);
					$('#btn-clear').attr('disabled', true);
				}
				if(divSelected.length == 1)
					$('#btn-rename').attr('disabled', false);
				else
					$('#btn-rename').attr('disabled', true);
				if($('div[data-file-type!="dir"].file-on-qiniu.selected').length > 0)
					$('#btn-insert').attr('disabled', false);
				else
					$('#btn-insert').attr('disabled', true);
				$('#btn-reload').attr('disabled', false);
			}
		});
	});
	// 清除已完成列表条目
	$('#clear-upload-info').click(function () {
		var tableDetail = $('#upload-file-detail');
		tableDetail.find('tr.success').remove();
		tableDetail.find('tr.warning').remove();
		tableDetail.find('tr.danger').remove();
    });
	// 重命名文件
	$('#btn-rename').click(function(){
		var divRename = $('div.file-on-qiniu.selected');
		var oldName = divRename.attr('data-file-name'),
			ftype = divRename.attr('data-file-type'),
			id = divRename.attr('data-file-id');
		var newName = prompt("请输入新名称（请勿使用特殊字符及系统保留字符）：", oldName);
		if(newName == null)
			return false;
		if(newName.trim(' ') == '') {
			alert('新名称不能为空！');
			return false;
		}
		var $this = $(this),
			nameCheck = true,
			fNode;
		if(ftype == 'dir')
			fNode = $('div.file-on-qiniu[data-file-type="dir"]:first');
		else
			fNode = $('div.file-on-qiniu[data-file-type!="dir"]:first');
		while(fNode && fNode.length && fNode.length > 0){
			if(newName == fNode.attr('data-file-name')){
				if(ftype == 'dir')
					alert('已存在相同名称的文件夹！');
				else
					alert('已存在相同名称的文件！');
				$this.attr('disabled', false);
				nameCheck = false;
				break;
			}
			if(ftype == 'dir')
				fNode =  fNode.next('div.file-on-qiniu[data-file-type="dir"]');
			else
				fNode =  fNode.next('div.file-on-qiniu[data-file-type!="dir"]');
		}
		if(!nameCheck)
			return;
		$.ajax({
			type: "get",
			//timeout: 3000,
			dataType: "json",
			url: $('#wp-admin-ajax-url').val(),
			data: {
				action: "wp_qiniu_file_rename",
				id: id,
				newname: newName,
				nonce: $('#wp_qiniu_ajax_nonce').val()
			},
			beforeSend: function () {
				$this.attr('disabled', true);
                $('#btn-reload').attr('disabled', true);
                $('#btn-insert').attr('disabled', true);
                $('#btn-delete').attr('disabled', true);
                $('#show-upload-area').attr('disabled', true);
            },
			success: function (data, textStatus) { //textStatus为success
				if(data['status']!='success'){
					alert('重命名失败！Error：'+ data['error']); //JSON.stringify(data['error'])
					return;
				}
				divRename.attr('data-file-name',data['fname']);
				divRename.find('div.file-text').text(data['fname']);
			},
			error: function (XMLHttpRequest, textStatus, errorThrown) {
				if(XMLHttpRequest.responseText) {
					var res = JSON.parse(XMLHttpRequest.responseText);
					alert('重命名失败！' + res['error'] + ", " + XMLHttpRequest.status + ": " + errorThrown + ", status: " + textStatus);
				} else
					alert('重命名失败！' + XMLHttpRequest.status + ": " + errorThrown + ", status: " + textStatus);
			},
			complete: function (XMLHttpRequest, textStatus) {
				// $this.attr('disabled', false);
                var divSelected = $('div.file-on-qiniu.selected');
                if(divSelected.length > 0)
                    $('#btn-delete').attr('disabled', false);
                else
                    $('#btn-delete').attr('disabled', true);
                if(divSelected.length == 1)
                    $('#btn-rename').attr('disabled', false);
                else
                    $('#btn-rename').attr('disabled', true);
                if($('div[data-file-type!="dir"].file-on-qiniu.selected').length > 0)
                    $('#btn-insert').attr('disabled', false);
                else
                    $('#btn-insert').attr('disabled', true);
                $('#btn-reload').attr('disabled', false);
                $('#show-upload-area').attr('disabled', false);
			}
		});
	});

	// 同步
	$('#btn-sync').click(function(){
		var rst = confirm('您确定要同步当前目录下的文件及文件信息吗？');
        if(rst == false)
            return;
        $('#btn-clear').click();
        var $this= $(this),
			divLoadMore = $('#load-more'),
            pid = divLoadMore.attr('data-pid'),
            interval = divLoadMore.attr('timer-int');
        $('#files-on-qiniu').empty();
        divLoadMore.attr('data-paged',1);

        if(interval != 'undefined')
            clearInterval(interval);

        $.ajax({
            type: "get",
            //timeout: 3000,
            dataType: "json",
            url: $('#wp-admin-ajax-url').val(),
            data: {
                action: "wp_qiniu_file_sync",
                pid : pid,
                nonce: $('#wp_qiniu_ajax_nonce').val()
            },
            beforeSend:function(){
                $('#btn-clear').attr('disabled', true);
                divLoadMore.attr('data-loading','true');
                divLoadMore.attr("class","page-navi-disable");
                divLoadMore.text('正在同步');
                function filesync(){
                    var text = divLoadMore.text();
                    if(text.length < 14 && text.length >= 4)
                        divLoadMore.text(text + ' .');
                    else
                        divLoadMore.text('正在同步 .');
                }
                interval = setInterval(filesync, 1000);
                divLoadMore.attr('timer-int',interval);
            },
            success:function(data, textStatus){
                clearInterval(interval);
                divLoadMore.attr("class", "page-navi");
                divLoadMore.attr('data-loading', 'false');
                if(data['status']!='success'){
                    divLoadMore.text('同步失败！  Error：'+ data['error']);
                } else {
                    alert('同步完成！');
                    divLoadMore.click();	// 开始加载文件列表
                }
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                clearInterval(interval);
                divLoadMore.attr('data-loading','false');
                divLoadMore.attr("class","page-navi");
                if(XMLHttpRequest.responseText) {
                    var res = JSON.parse(XMLHttpRequest.responseText);
                    divLoadMore.text('同步失败！' + res['error'] + ", " + XMLHttpRequest.status + ": " + errorThrown + ", status: " + textStatus);
                } else
                    divLoadMore.text('同步失败！' + XMLHttpRequest.status + ": " + errorThrown + ", status: " + textStatus);
            },
            complete: function (XMLHttpRequest, textStatus) {
                divLoadMore.removeAttr('timer-int');
            }
        });
    });
});
