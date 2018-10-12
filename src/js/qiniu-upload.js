//$(function() {
jQuery(function($) {
    $(document).ready(function () {
        var pluginUrl = $('#wp-qiniu-plugin-url').val(),
            splitchar = $('#wp-qiniu-style-split-char').val(),
            thumbnailname = $('#wp-qiniu-thumbnail-style').val(),
            watermarkname = $('#wp-qiniu-watermark-style').val(),
            storageDomain = $('#wp-qiniu-storage-domain').val(),
            ajaxUrl = $('#wp-admin-ajax-url').val(),
			ajaxNonce = $('#wp_qiniu_ajax_nonce').val(),
            thumbnailStyle = (thumbnailname) ? splitchar + thumbnailname : '?imageView2/1/w/100/h/100',
            watermarkStyle = (watermarkname) ? splitchar + watermarkname : '';
        var chunk_size = 4 * 1024 * 1024;
        var uploadUrl = $('#wp-qiniu-upload-url').val();
        var indexCount = 0;
        var resume = false;
        var blockSize;
        var token = '';
        var config = {
            useCdnDomain: true,
            disableStatisticsReport: false,
            retryCount: 6
            // region: qiniu.region.z2
        };
        var putExtra = {
            fname: "",
            params: {},
            mimeType: null
        };
            
        var uploader = new plupload.Uploader({
            url: uploadUrl,
            runtimes: 'html5,flash,html4',						// 上传模式,依次退化
            browse_button: 'upload-pickfiles',					// 上传选择的点选按钮，**必需**

            // domain: storageDomain,	                            //bucket 域名，下载资源时用到，**必需**
            // downtoken_url: $('#wp-admin-ajax-url').val()+"?action=wp_qiniu_get_download_url",							// Ajax请求downToken的Url，私有空间时使用，JS-SDK将向该地址POST文件的key和domain，服务端返回的JSON必须包含url字段，url值为该文件的下载地址
            container: 'upload-container',								// 上传区域DOM ID，默认是browser_button的父元素，
            max_file_size: '512mb',								// 最大文件体积限制
            flash_swf_url: '../wp-includes/js/plupload/plupload.flash.swf',				// 引入flash,相对路径  pluginUrl + '/lib/plupload/Moxie.swf'
            max_retries: 3,										// 上传失败最大重试次数
            dragdrop: true,										// 开启可拖曳上传
            drop_element: 'upload-pickfiles',							// 拖曳上传区域元素的ID，拖曳文件或文件夹后可触发上传
            chunk_size: chunk_size,									// 分块上传时，每片的体积
            multi_selection: true,								// 设置一次只能选择一个文件，还是可以多选
            multipart_params: {
                // token从服务端获取，没有token无法上传
                token: token
            },
            //filters : {		// 设置文件过滤选项
            //	max_file_size : '100mb',
            //	prevent_duplicates: true,    // Specify what files to browse for
            //	mime_types: [
            //		{title : "Image files", extensions : "jpg,gif,png"},
            //		{title : "Zip files", extensions : "zip"},
            //		{title : "Video files", extensions : "flv,mpg,mpeg,avi,wmv,mov,asf,rm,rmvb,mkv,m4v,mp4"}, // 限定flv,mpg,mpeg,avi,wmv,mov,asf,rm,rmvb,mkv,m4v,mp4后缀格式上传
            //	]
            //},
            init: {
                PostInit: function() {
                    console.log("upload init");
                },
                FilesAdded: function (up, files) {		// 当文件添加到上传队列后触发
                    resume = false;
                    $('#upload-detail').show();//$('#fsUploadProgress').show();
                    $('#upload-success').hide();
                    var divLoadMore = $('#load-more'),
                        pid = divLoadMore.attr('data-pid'),
                        path = divLoadMore.attr('data-current-path');
                    plupload.each(files, function (file) {
                        // 文件添加进队列后,处理相关的事情
                        file.pid = pid;
                        file.path = path;
                        file.overwrite = false;
                        file.canceled = false;
                        var progress = new FileProgress(file, 'fsUploadProgress');
                        progress.setStatus("等待...");
                        progress.bindUploadCancel(up);
                    });
                },
                FileUploaded: function(up, file, info) {
                    console.log(info);
                },
                UploadComplete: function(up, files) {
                    // Called when all files are either uploaded or failed
                    console.log("[完成]");
                },
                Error: function(up, err) {
                    console.log(err.response);
                }
            }
        });
// domain 为七牛空间（bucket)对应的域名，选择某个空间后，可通过"空间设置->基本设置->域名设置"查看获取
// uploader 为一个plupload对象，继承了所有plupload的方法，参考http://plupload.com/docs
        uploader.init();
        uploader.bind("FilesAdded", function (uploader, files) {
            $('#uploader-ope').show();
            setTimeout(function () {
                startUpload(uploader);
            }, 0);
            uploader.refresh();
        });
        uploader.bind('Error',function(uploader, err){
            console.log('file upload error.');
            //上传出错时,处理相关的事情
            $('#upload-detail').show();//$('#fsUploadProgress').show();
            var progress = new FileProgress(err.file, 'fsUploadProgress');
            progress.setError();
            var errTip = getErrTip(err.status);
            progress.setStatus(errTip); // + err.response
            console.log(err.response);
        });
        uploader.bind("BeforeUpload", function(uploader, file) {
            // 每个文件上传前,处理相关的事情
            var progress = new FileProgress(file, 'fsUploadProgress');
            if (file.canceled) {
                progress.fileProgressWrapper.find('td:eq(2) .progressCancel').click();
                return false;
            } else {
                if (uploader.runtime === 'html5' && chunk_size) {
                    progress.setChunkProgess(chunk_size);
                }
            }
            file.startTime = new Date();
            file.speed = file.speed || 0;
            var id = file.id;
            token = getUpToken(uploader, file);
            var key = file.key;
            console.log('file.key', file.key)
            putExtra.params["x:name"] = key.split(".")[0];


            // chunk_size = uploader.getOption("chunk_size");

            var directUpload = function() {
                var multipart_params_obj = {};
                multipart_params_obj.token = token;
                // filterParams 返回符合自定义变量格式的数组，每个值为也为一个数组，包含变量名及变量值
                var customVarList = qiniu.filterParams(putExtra.params);
                for (var i = 0; i < customVarList.length; i++) {
                    var k = customVarList[i];
                    multipart_params_obj[k[0]] = k[1];
                }
                multipart_params_obj.key = key;
                uploader.setOption({
                    url: uploadUrl,
                    multipart: true,
                    multipart_params: multipart_params_obj
                });
            };

            var resumeUpload = function() {
                blockSize = chunk_size;
                initFileInfo(file);
                if(blockSize === 0){
                    mkFileRequest(file)
                    uploader.stop()
                    return
                }
                resume = true;
                var multipart_params_obj = {};
                // 计算已上传的chunk数量
                var index = Math.floor(file.loaded / chunk_size);
                // var dom_total = $(board[id])
                //     .find("#totalBar")
                //     .children("#totalBarColor");
                // if (board[id].start != "reusme") {
                //     $(board[id])
                //         .find(".fragment-group")
                //         .addClass("hide");
                // }
                // dom_total.css(
                //     "width",
                //     file.percent + "%"
                // );
                // 初始化已上传的chunk进度
                // for (var i = 0; i < index; i++) {
                //     var dom_finished = $(board[id])
                //         .find(".fragment-group li")
                //         .eq(i)
                //         .find("#childBarColor");
                //     dom_finished.css("width", "100%");
                // }
                var headers = qiniu.getHeadersForChunkUpload(token)
                uploader.setOption({
                    url: uploadUrl + "/mkblk/" + blockSize,
                    multipart: false,
                    required_features: "chunks",
                    headers: {
                        Authorization: "UpToken " + token
                    },
                    multipart_params: multipart_params_obj
                });
            };
            // 判断是否采取分片上传
            if (
                (uploader.runtime === "html5" || uploader.runtime === "flash") &&
                chunk_size
            ) {
                if (file.size < chunk_size) {
                    directUpload();
                } else {
                    resumeUpload();
                }
            } else {
                console.log(
                    "directUpload because file.size < chunk_size || is_android_weixin_or_qq()"
                );
                directUpload();
            }
        });
        uploader.bind("ChunkUploaded", function(up, file, info) {
            var res = JSON.parse(info.response);
            var leftSize = info.total - info.offset;
            var chunk_size = uploader.getOption && uploader.getOption("chunk_size");
            if (leftSize < chunk_size) {
                up.setOption({
                    url: uploadUrl + "/mkblk/" + leftSize
                });
            }
            up.setOption({
                headers: {
                    Authorization: "UpToken " + token
                }
            });
            // 更新本地存储状态
            var localFileInfo = JSON.parse(localStorage.getItem(file.name))|| [];
            localFileInfo[indexCount] = {
                ctx: res.ctx,
                time: new Date().getTime(),
                offset: info.offset,
                percent: file.percent
            };
            indexCount++;
            localStorage.setItem(file.name, JSON.stringify(localFileInfo));
        });
        // 每个事件监听函数都会传入一些很有用的参数，
        // 我们可以利用这些参数提供的信息来做比如更新UI，提示上传进度等操作
        uploader.bind("UploadProgress", function(uploader, file) {
            var currentTime = (new Date).getTime();
            var i = currentTime - file.startTime, n = file.loaded || 0;
            resume && (n = file.loaded - file.resumeFilesize);
            file.speed = (n / i * 1e3).toFixed(0) || 0;

            // 每个文件上传时,处理相关的事情
            var progress = new FileProgress(file, 'fsUploadProgress');
            progress.setProgress(file.percent + "%", file.speed, chunk_size);
        });
        uploader.bind("FileUploaded", function(uploader, file, info) {
            if (resume) {
                mkFileRequest(file)
            } else {
                var res = $.parseJSON(info.response);
                uploadFinish(res, file);
            }

        });
        uploader.bind("UploadComplete", function () {
            //队列文件处理完毕后,处理相关的事情
            $('#upload-success').show();
            $('#uploader-ope').hide();
            // 添加已上传文件的显示，并返回至文件列表窗口
            //$('#show-upload-area').click();
        });

        function startUpload(uploader) {
            uploader.start();
            var btn = $('#uploader-ope');
            btn.text('暂停上传');
            btn.css('background-color','yellow');
            btn.css('color', 'black');
            btn.unbind('click');
            btn.click(function () {
                stopUpload(uploader);
            });
        }
        function stopUpload(uploader) {
            uploader.stop();
            var btn = $('#uploader-ope');
            btn.text('继续上传');
            btn.css('background-color','#0085ba');
            btn.css('color', 'white');
            btn.unbind('click');
            btn.click(function () {
                startUpload(uploader);
            });
        }
        function getUpToken(uploader, file) {
            var token = '';
            $.ajax({
                type: "POST",
                url: ajaxUrl,
                async : false,
                data: {
                    action: 'wp_qiniu_get_uptoken',
                    pid: file.pid,
                    fname: file.name,
                    nonce: ajaxNonce
                },
                error: function (e) {
                    alert('获取文件上传token失败！');
                    // console.log(e);
                },
                success: function (res) {
                    // var res = JSON.parse(response);
                    //console.log('custom uptoken_func:' + res.uptoken);
                    if (res.status === 'success') {
                        if (res.exist) {
                            var overwrite = confirm('已存在同名文件，覆盖原有文件？');
                            if (overwrite) {
                                file.overwrite = true;
                            } else {
                                file.canceled = true;
                            }
                        } else
                            file.overwrite = false;
                        token = res.uptoken;
                        file.fname = res.fname;
                        file.key = file.path + file.fname;
                        // console.log(res)
                    } else {
                        alert(res.error);
                        // console.log(res);
                    }
                }
            });
            return token;
        }
        function uploadFinish(info, file) {
            localStorage.removeItem(file.name)

            // 每个文件上传成功后,处理相关的事情
            // 其中 info 是文件上传成功后，服务端返回的json，形式如
            // {
            //    "hash": "Fh8xVqod2MQ1mocfI4S4KpRL6D98",
            //    "key": "gogopher.jpg"
            //  }
            // 参考http://developer.qiniu.com/docs/v6/api/overview/up/response/simple-response.html
            //查看简单反馈
            // var domain = up.getOption('domain');
            // var res = parseJSON(info);
            // var sourceLink = domain + res.key; 获取上传成功后的文件的Url
            if (file.overwrite) {
                $('div.file-on-qiniu[data-file-name="' + file.name + '"]').each(function () {
                    if ($(this).attr('data-file-type') != 'dir')
                        $(this).remove();
                });
            }
            if(info.ncb){   // 非七牛回调服务器模式，
                info.action = 'wp_qiniu_upload_complete';
                info.nonce =ajaxNonce;
                $.ajax({
                    type: "POST",
                    url: ajaxUrl,
                    async : false,
                    data: info,
                    error: function (e) {
                        alert('文件上传成功，但提交文件信息失败！');
                    },
                    success: function (res) {
                        if (res.status === 'success') {
                            var progress = new FileProgress(file, 'fsUploadProgress');
                            progress.setComplete(res);
                        } else {
                            alert(res.error);
                        }
                    }
                });
            } else {
                var progress = new FileProgress(file, 'fsUploadProgress');
                progress.setComplete(info);
            }
        }
        function initFileInfo(file) {
            var localFileInfo = JSON.parse(localStorage.getItem(file.name))|| [];
            indexCount = 0;
            var length = localFileInfo.length;
            if (length) {
                var clearStatus = false;
                for (var i = 0; i < localFileInfo.length; i++) {
                    indexCount++;
                    if (isExpired(localFileInfo[i].time)) {
                        clearStatus = true;
                        localStorage.removeItem(file.name);
                        break;
                    }
                }
                if(clearStatus){
                    indexCount = 0;
                    return;
                }
                file.loaded = localFileInfo[length - 1].offset;
                var leftSize = file.size - file.loaded;
                if(leftSize < chunk_size){
                    blockSize = leftSize;
                }
                file.percent = localFileInfo[length - 1].percent;
                file.resumeFilesize = file.loaded;
                return;
            }else{
                indexCount = 0;
                file.percent = 0;
                file.resumeFilesize = 0;
            }
        }
        function mkFileRequest(file){
            // 调用sdk的url构建函数
            var requestUrl = qiniu.createMkFileUrl(
                uploadUrl,
                file.size,
                file.key,
                putExtra
            );
            var ctx = [];
            var id = file.id;
            var stoInfo = localStorage.getItem(file.name);
            if(stoInfo) {
                var local = JSON.parse(stoInfo);
                for (var i = 0; i < local.length; i++) {
                    ctx.push(local[i].ctx);
                }
            }
            // 设置上传的header信息
            var headers = qiniu.getHeadersForMkFile(token);
            $.ajax({url: requestUrl, type: "POST",  headers: headers, data: ctx.join(","), success: function(res){
                    uploadFinish(res, file);
                },error: function (e) {
                    console.log('file upload error.');
                    //上传出错时,处理相关的事情
                    $('#upload-detail').show();//$('#fsUploadProgress').show();
                    var progress = new FileProgress(file, 'fsUploadProgress');
                    progress.setError();
                    var errTip = getErrTip(e.status);
                    progress.setStatus(errTip);// + e.response
                    console.log(e.response);
                }
            })
        }
        function isExpired(time){
            var expireAt = time + 3600 * 24* 1000;
            return new Date().getTime() > expireAt;
        }
        function getErrTip(status) {
            switch (status){
                case 298:
                    return '部分操作执行成功。';
                case 400:
                    return '请求报文格式错误。';
                case 401:
                    return '认证授权失败。';
                case 403:
                    return '权限不足，拒绝访问。';
                case 404:
                    return '资源不存在。';
                case 405:
                    return '请求方式错误。';
                case 406:
                    return '上传的数据 CRC32 校验错误。';
                case 413:
                    return '请求资源大小大于指定的最大值。';
                case 419:
                    return '用户账号被冻结。';
                case 478:
                    return '镜像回源失败。';
                case 502:
                    return '错误网关。';
                case 503:
                    return '服务端不可用。';
                case 504:
                    return '服务端操作超时。';
                case 573:
                    return '单个资源访问频率过高。';
                case 579:
                    return '上传成功但是回调失败。';
                case 599:
                    return '服务端操作失败。';
                case 608:
                    return '资源内容被修改。';
                case 612:
                    return '指定资源不存在或已被删除。';
                case 614:
                    return '目标资源已存在。';
                case 630:
                    return '已创建的空间数量达到上限，无法创建新空间。';
                case 631:
                    return '指定空间不存在。';
                case 640:
                    return '调用列举资源(list)接口时，指定非法的marker参数。';
                case 701:
                    return '在断点续上传过程中，后续上传接收地址不正确或ctx信息已过期。。';
                default:
                    return '未知错误（错误码：'+ status +'）。';
            }
        }

        $('#upload-pickfiles').on(
            'dragenter',
            function (e) {
                e.preventDefault();
                $('#upload-pickfiles').addClass('draging');
                e.stopPropagation();
            }
        ).on('drop', function (e) {
            e.preventDefault();
            $('#upload-pickfiles').removeClass('draging');
            e.stopPropagation();
        }).on('dragleave', function (e) {
            e.preventDefault();
            $('#upload-pickfiles').removeClass('draging');
            e.stopPropagation();
        }).on('dragover', function (e) {
            e.preventDefault();
            $('#upload-pickfiles').addClass('draging');
            e.stopPropagation();
        });

        $('body').on('click', 'table button.btn', function () {
            $(this).parents('tr').next().toggle();
        });
        // 清除已完成列表条目
        $('#clear-upload-info').click(function () {
            $('#upload-success').hide();
            var tableDetail = $('#upload-file-detail');
            tableDetail.find('tr.success').remove();
            tableDetail.find('tr.warning').remove();
            tableDetail.find('tr.danger').remove();
        });
        $('#uploader-ope').click(function () {
            startUpload(uploader);
        });

        function FileProgress(file, targetID) {
            this.fileProgressID = file.id;
            this.file = file;

            this.opacity = 100;
            this.height = 0;
            this.fileProgressWrapper = $('#' + this.fileProgressID);
            if (!this.fileProgressWrapper.length) {
                this.fileProgressWrapper = $('<tr></tr>');
                var Wrappeer = this.fileProgressWrapper;
                Wrappeer.attr('id', this.fileProgressID).addClass('progressContainer');

                var progressText = $("<td/>");
                progressText.addClass('progressName').text(file.name);


                var fileSize = plupload.formatSize(file.size).toUpperCase();
                var progressSize = $("<td/>");
                progressSize.addClass("progressFileSize").text(fileSize);

                var progressBarTd = $("<td colspan='2'/>");
                var progressBarBox = $("<div/>");
                progressBarBox.addClass('info');
                var progressBarWrapper = $("<div/>");
                progressBarWrapper.addClass("progress progress-striped");

                var progressBar = $("<div/>");
                progressBar.addClass("progress-bar progress-bar-info")
                    .attr('role', 'progressbar')
                    .attr('aria-valuemax', 100)
                    .attr('aria-valuenow', 0)
                    .attr('aria-valuein', 0)
                    .width('0%');

                var progressBarPercent = $('<span class="sr-only" />');
                progressBarPercent.text(fileSize);

                //var progressCancel = $('<a href=javascript:;/>');
                //progressCancel.html('<img class="upload-cancel" src="' + pluginUrl + 'img/cancel.png" title="取消上传"/>');
				var progressCancel = $('<a href=javascript:; title="取消上传"><span/></a>');
                progressCancel.show().addClass('progressCancel');

                progressBar.append(progressBarPercent);
                progressBarWrapper.append(progressBar);
                progressBarBox.append(progressBarWrapper);
                progressBarBox.append(progressCancel);

                var progressBarStatus = $('<div class="status text-center"/>');
                progressBarBox.append(progressBarStatus);
                progressBarTd.append(progressBarBox);

                Wrappeer.append(progressText);
                Wrappeer.append(progressSize);
                Wrappeer.append(progressBarTd);

                $('#' + targetID).append(Wrappeer);
            } else {
                this.reset();
            }

            this.height = this.fileProgressWrapper.offset().top;
            this.setTimer(null);
        }

        FileProgress.prototype.setTimer = function (timer) {
            this.fileProgressWrapper.FP_TIMER = timer;
        };

        FileProgress.prototype.getTimer = function () {
            return this.fileProgressWrapper.FP_TIMER || null;
        };

        FileProgress.prototype.reset = function () {
            this.fileProgressWrapper.attr('class', "progressContainer");
            this.fileProgressWrapper.find('td .progress .progress-bar-info').attr('aria-valuenow', 0).width('0%').find('span').text('');
            this.appear();
        };

        FileProgress.prototype.setChunkProgess = function (chunk_size) {
            var chunk_amount = Math.ceil(this.file.size / chunk_size);
            if (chunk_amount === 1) {
                return false;
            }

            var viewProgess = $('<button class="btn btn-default button">查看分块上传进度</button>');

            var progressBarChunkTr = $('<tr class="chunk-status-tr"><td colspan=3></td></tr>');
            var progressBarChunk = $('<div/>');
            for (var i = 1; i <= chunk_amount; i++) {
                var col = $('<div class="col-md-2"/>');
                var progressBarWrapper = $('<div class="progress progress-striped"></div>');

                var progressBar = $("<div/>");
                progressBar.addClass("progress-bar progress-bar-info text-left")
                    .attr('role', 'progressbar')
                    .attr('aria-valuemax', 100)
                    .attr('aria-valuenow', 0)
                    .attr('aria-valuein', 0)
                    .width('0%')
                    .attr('id', this.file.id + '_' + i)
                    .text('');

                var progressBarStatus = $('<span/>');
                progressBarStatus.addClass('chunk-status').text();

                progressBarWrapper.append(progressBar);
                progressBarWrapper.append(progressBarStatus);

                col.append(progressBarWrapper);
                progressBarChunk.append(col);
            }

            if (!this.fileProgressWrapper.find('td:eq(2) .btn-default').length) {
                this.fileProgressWrapper.find('td>div').append(viewProgess);
            }
            progressBarChunkTr.hide().find('td').append(progressBarChunk);
            progressBarChunkTr.insertAfter(this.fileProgressWrapper);

        };

        FileProgress.prototype.setProgress = function (percentage, speed, chunk_size) {
            this.fileProgressWrapper.attr('class', "progressContainer green");

            var file = this.file;
            var uploaded = file.loaded;

            var size = plupload.formatSize(uploaded).toUpperCase();
            var formatSpeed = plupload.formatSize(speed).toUpperCase();
            var progressbar = this.fileProgressWrapper.find('td .progress').find('.progress-bar-info');
            if (this.fileProgressWrapper.find('.status').text() === '已取消上传') {
                return;
            }
            this.fileProgressWrapper.find('.status').text("已上传: " + size + " 上传速度： " + formatSpeed + "/s");
            percentage = parseInt(percentage, 10);
            if (file.status !== plupload.DONE && percentage === 100) {
                percentage = 99;
            }

            progressbar.attr('aria-valuenow', percentage).css('width', percentage + '%');

            if (chunk_size) {
                var chunk_amount = Math.ceil(file.size / chunk_size);
                if (chunk_amount === 1) {
                    return false;
                }
                var current_uploading_chunk = Math.ceil(uploaded / chunk_size);
                var pre_chunk, text;

                for (var index = 0; index < current_uploading_chunk; index++) {
                    pre_chunk = $('#' + file.id + "_" + index);
                    pre_chunk.width('100%').removeClass().addClass('alert-success').attr('aria-valuenow', 100);
                    text = "块" + index + "上传进度100%";
                    pre_chunk.next().html(text);
                }

                var currentProgessBar = $('#' + file.id + "_" + current_uploading_chunk);
                var current_chunk_percent;
                if (current_uploading_chunk < chunk_amount) {
                    if (uploaded % chunk_size) {
                        current_chunk_percent = ((uploaded % chunk_size) / chunk_size * 100).toFixed(2);
                    } else {
                        current_chunk_percent = 100;
                        currentProgessBar.removeClass().addClass('alert-success');
                    }
                } else {
                    var last_chunk_size = file.size - chunk_size * (chunk_amount - 1);
                    var left_file_size = file.size - uploaded;
                    if (left_file_size % last_chunk_size) {
                        current_chunk_percent = ((uploaded % chunk_size) / last_chunk_size * 100).toFixed(2);
                    } else {
                        current_chunk_percent = 100;
                        currentProgessBar.removeClass().addClass('alert-success');
                    }
                }
                currentProgessBar.width(current_chunk_percent + '%');
                currentProgessBar.attr('aria-valuenow', current_chunk_percent);
                text = "块" + current_uploading_chunk + "上传进度" + current_chunk_percent + '%';
                currentProgessBar.next().html(text);
            }

            this.appear();
        };

        FileProgress.prototype.setComplete = function (res) {
            var td = this.fileProgressWrapper.find('td:eq(2)'),
                tdProgress = td.find('.progress');

            var domain = storageDomain;
            var url, str;
            if (res.url) {
                url = res.url;
            } else {
                url = domain + res.key;
            }
            str = "<div><strong>原文件链接：</strong><a href=" + url + " target='_blank' > " + domain + res.key + "</a></div>" +
                    "<div class=hash><strong>文件Hash值：</strong>" + res.hash + "</div>" + 
                    "<div class=hash><strong>文件Key：</strong>" + res.key + "</div>" +
                    "<br/><div><strong></strong></div>";

            tdProgress.html(str).removeClass().next().next('.status').hide();
            td.find('.progressCancel').hide();
            td.find('.btn').parents('tr').next().remove();
            td.find('.btn').remove();

            var progressNameTd = this.fileProgressWrapper.find('.progressName');
            this.fileProgressWrapper.addClass('success');

            var Wrapper = $('<div class="Wrapper"/>');
            var imgWrapper = $('<div class="imgWrapper col-md-3"/>');
            var linkWrapper = $('<a class="linkWrapper" target="_blank"/>');
            var showImg = $('<img src="' + pluginUrl + 'img/loading.gif"/>');

            progressNameTd.append(Wrapper);

            var imgArr = '.|jpg|jpeg|png|gif|bmp|',
                videoArr = '.|asf|avi|flv|mkv|mov|mp4|wmv|3gp|3g2|mpeg|ts|rm|rmvb|m3u8|',
                audioArr = '.|ogg|mp3|wma|wav|mp3pro|mid|midi|',
                ftype = res.fname.substring(res.fname.lastIndexOf('.') + 1).toLowerCase();

            if (imgArr.indexOf('|' + ftype + '|') > 0) {
                ftype = 'image';
            }
            else if (videoArr.indexOf('|' + ftype + '|') > 0) {
                ftype = 'video';
            }
            else if (audioArr.indexOf('|' + ftype + '|') > 0) {
                ftype = 'audio';
            }
            else
                ftype = 'file';

            if (ftype !== 'image' && ftype !== 'video') {
                showImg.attr('src', pluginUrl + 'img/' + ftype + '.png');
                Wrapper.addClass('default');
                imgWrapper.append(showImg);
                Wrapper.append(imgWrapper);
            } else {
                if(ftype === 'image') {
                    linkWrapper.append(showImg);
                    imgWrapper.append(linkWrapper);
                    Wrapper.append(imgWrapper);
                    linkWrapper.attr('href', url).attr('title', '查看原图');

                    showImg.attr('src', domain + encodeURI(res.key) + thumbnailStyle);
                } else {
                    showImg.attr('src', pluginUrl + 'img/' + ftype + '.png');
                    Wrapper.addClass('default');
                    imgWrapper.append(showImg);
                    Wrapper.append(imgWrapper);
                }
                var infoWrapper = $('<div class="infoWrapper col-md-6"></div>');

                if (watermarkStyle && ftype === 'image') {
                    var waterLink = $('<a href="" target="_blank">查看水印图片</a>');
                    waterLink.attr('href', domain + encodeURI(res.key) + watermarkStyle).attr('title', '查看水印图片');
                    infoWrapper.append(waterLink);
                }
                var infoArea = $('<div/>');
                var infoInner = '<div>格式：<span class="origin-format">' + res.format + '</span></div>' +
                    '<div>宽度：<span class="orgin-width">' + res.width + 'px</span></div>' +
                    '<div>高度：<span class="origin-height">' + res.height + 'px</span></div>';
                infoArea.html(infoInner);
                infoWrapper.append(infoArea);
                Wrapper.append(infoWrapper);
            }

            var fnode_html = '<div class="file-on-qiniu' + '" data-file-name="' + res.fname + '" data-file-type="' + ftype + '" data-file-id="' + res.id + '" data-file-mime="' + res.mimeType + '">';
            fnode_html += '<div class="file-thumbnail">';
            if (ftype === 'image')
                fnode_html += '<img src="' + domain + encodeURI(res.key) + thumbnailStyle + '" />';
            else
                fnode_html += '<img src="' + pluginUrl + 'img/' + ftype + '.png" />';

            fnode_html += '</div>';
            fnode_html += '<div class="file-name"><div class="file-text">';
            fnode_html += res.fname;
            fnode_html += '</div></div>';
            fnode_html += '</div>';

            $('#files-on-qiniu').append(fnode_html);
        };
        FileProgress.prototype.setError = function () {
            this.fileProgressWrapper.find('td:eq(2)').attr('class', 'text-warning');
            this.fileProgressWrapper.find('td:eq(2) .progress').css('width', 0).hide();
            this.fileProgressWrapper.find('button').hide();
            this.fileProgressWrapper.next('.chunk-status-tr').hide();
            this.fileProgressWrapper.addClass('danger');
        };

        FileProgress.prototype.setCancelled = function (manual) {
            var progressContainer = 'progressContainer';
            if (!manual) {
                progressContainer += ' red';
            }
            this.fileProgressWrapper.attr('class', progressContainer);
            this.fileProgressWrapper.find('td .progress').remove();
            this.fileProgressWrapper.find('td:eq(2) .btn-default').hide();
            this.fileProgressWrapper.find('td:eq(2) .progressCancel').hide();
            this.fileProgressWrapper.next('.chunk-status-tr').hide();
        };

        FileProgress.prototype.setStatus = function (status, isUploading) {
            if (!isUploading) {
                this.fileProgressWrapper.find('.status').text(status).attr('class', 'status text-left');
            }
        };

        // 绑定取消上传事件
        FileProgress.prototype.bindUploadCancel = function (up) {
            var self = this;
            if (up) {
                self.fileProgressWrapper.find('td:eq(2) .progressCancel').on('click', function () {
                    self.setCancelled(false);
                    self.setStatus("已取消上传");
                    self.fileProgressWrapper.find('.status').css('left', '0');
                    up.removeFile(self.file);
                    self.fileProgressWrapper.addClass('warning');
                    if(up.files.length === 0) {
                        $('#uploader-ope').hide();
                    }
                });
            }
        };

        FileProgress.prototype.appear = function () {
            if (this.getTimer() !== null) {
                clearTimeout(this.getTimer());
                this.setTimer(null);
            }

            if (this.fileProgressWrapper[0].filters) {
                try {
                    this.fileProgressWrapper[0].filters.item("DXImageTransform.Microsoft.Alpha").opacity = 100;
                } catch (e) {
                    // If it is not set initially, the browser will throw an error.  This will set it if it is not set yet.
                    this.fileProgressWrapper.css('filter', "progid:DXImageTransform.Microsoft.Alpha(opacity=100)");
                }
            } else {
                this.fileProgressWrapper.css('opacity', 1);
            }

            this.fileProgressWrapper.css('height', '');

            this.height = this.fileProgressWrapper.offset().top;
            this.opacity = 100;
            this.fileProgressWrapper.show();

        };
    });
});
