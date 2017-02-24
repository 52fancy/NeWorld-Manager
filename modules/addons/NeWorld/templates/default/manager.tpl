<link rel="stylesheet" href="{$templates}assets/css/style.css?v1">
<link rel="stylesheet" href="{$templates}assets/sweetalert/sweetalert.css">
<script type="text/javascript" src="{$templates}assets/sweetalert/sweetalert.min.js"></script>
<script type="text/javascript">
$(function(){
    $("#submitkey").click(function() { //当按钮button被点击时的处理函数
        if ( checkLicense() ) {
            verifyLicense();
            //button被点击时执行postdata函数
            $("#licenseKey").val('').focus(); //提交清空内容
        }
    });
    actionButton();
});

    function verifyLicense() { //提交数据函数
        var license = $("#licenseKey").val();
        $.ajax({ //调用jQuery的ajax方法
            type: "POST", //设置ajax方法提交数据的形式
	        timeout : 150000, //超时时间设置，单位毫秒
            url: "{$NeWorld}public/verify.php", //把数据提交
            data: "license=" + license + "&verify={$verify}", //输入框writer中的值作为提交的数据
            dataType: 'json',
            beforeSend:function(XMLHttpRequest){
                $("body").append('<div class="loading"><div class="item-inner"><div class="item-loader-container"><div class="la-pacman la-2x"><div></div><div></div><div></div><div></div><div></div><div></div></div></div><h5>正在验证授权...</h5></div></div>');
            },
            success: function(data) { //提交成功后的回调。
                if (data.status == 'success') {
                    var html = '<tr id="#product_' + data.id + '"><td>' + data.softname + '</td><td>' + license + '</td><td class="hidden-xs">' + data.date + '</td><td class="text-center" id="currentversion_' + data.id + '">-</td><td class="text-center" id="lastversion_' + data.id + '">' + data.version + '</td><td class="text-center"><div class="btn btn-warning btn-xs" data-type="NeWorld" data-action="install" data-license="' + license + '" data-name="' + data.softname + '" data-id="' + data.id + '" id="button_' + data.id + '"><span class="glyphicon glyphicon-floppy-save"></span> 安装</div> <div class="btn btn-info btn-xs" data-type="NeWorld" data-action="check" data-license="' + license + '" data-name="' + data.softname + '" data-id="' + data.id + '"><span class="glyphicon glyphicon-floppy-saved"></span> 检测</div> <div class="btn btn-danger btn-xs" data-type="NeWorld" data-action="delete" data-license="' + license + '" data-name="' + data.softname + '" data-id="' + data.id + '"><span class="glyphicon glyphicon-floppy-remove"></span> 删除</div></td></tr>';
                    $("#license-list tbody").append(html);
                    actionButton();
                } else {
	                swal("操作失败", data.info, "error");
                }
                $('.loading').fadeOut();
            },
            complete : function(XMLHttpRequest,status){
	            //请求完成后最终执行参数
				if(status=='timeout'){
					//超时,status还有success,error等值的情况
					ajaxTimeoutTest.abort();
                    swal({
	                    title: "操作失败",
	                    text: "操作超时，请检查设置是否正确。",
	                    type: "error",
                    });
				}
			}
        });
    }

    function actionButton() {
        $("[data-type='NeWorld']").click(function() { // 匿名函数
            submitAction($(this).attr('data-action'), $(this).attr('data-license'), $(this).attr('data-name'), $(this).attr('data-id'));
        });
    }

    function submitAction(action, license, name, id) {
        // 别名
        var cname = '网页出现未知错误，请刷新重试';
        var tips = '';
        switch (action)
        {
            case 'install':
                cname 	= '安装';
                text 	= '主题模块将自动安装在网站目录<br/>请确保目录具有写入权限。';
                tips 	= '已成功安装至当前网站，请按照文档完成其他设置';
                break;
            case 'reinstall':
                cname 	= '重装';
                text 	= '主题模块将自动重装在网站目录<br/>请确定您备份过修改过的文件。';
                tips 	= '已重新安装完毕';
                break;
            case 'update':
                cname 	= '更新';
                text 	= '主题模块将自动更新在网站目录<br/>请确定您备份过修改过的文件。';
                tips 	= '已成功更新至最新版';
                break;
            case 'delete':
                cname 	= '删除';
                text 	= '删除授权许可可能会导致您的模块不正常运行<br/>请确保关闭模块后再进行删除。';
                tips 	= '已安全移除授权许可';
                break;
            case 'check':
                cname 	= '检测';
                text 	= '更新授权状态或检测最新版本。';
                tips 	= '已更新版本与授权信息<br/>刷新当前页面即可查阅最新内容';
                break;
            default:
                 swal(cname);
        }
        swal({
			title: "正在进行 " + cname + " 操作",
			text: text,
			type: "info",
			html: true,
			showCancelButton: true,
			closeOnConfirm: false,
			confirmButtonText: "确定",
			cancelButtonText: "取消",
			showLoaderOnConfirm: true,
		},
		function(){
	        $.ajax({ //调用jquery的ajax方法
	            type: "POST", //设置ajax方法提交数据的形式
	            timeout : 150000, //超时时间设置，单位毫秒
	            url: "{$NeWorld}public/action.php", //把数据提交
	            data: "action=" + action + "&license=" + license + "&verify={$verify}" + "&id=" + id, //输入框writer中的值作为提交的数据
	            dataType: 'json',
	            success: function(data) { //提交成功后的回调。
	                if (data.status == 'success') {
	                    var button = $("#button_" + id);
	                    switch (action)
	                    {
	                        case 'install':
	                        case 'reinstall':
	                        case 'update':
	                            $("#currentversion_" + id).html(data.version);
	                            button.html('<span class="glyphicon glyphicon-floppy-save"></span> 重装');
	                            break;
	                        case 'delete':
	                            // 先使用 JS 的选择器
	                            var product = document.getElementById("#product_" + id);
	
	                            // 判断是否为 NULL
	                            if (product) {
	                                product.remove();
	                            } else {
	                                // 如果是 NULL 就用 JQ 选择器
	                                $("#product_" + id).remove();
	                            }
	                            break;
	                        case 'check':
	                            $("#lastversion_" + id).html(data.version);
	                            break;
	                        default:
	                            break;
	                    }
	                    swal({
		                    title: name + '已' + cname,
		                    text: tips,
		                    type: "success",
		                    html: true
	                    });
	                } else {
	                    swal({
		                    title: "操作失败",
		                    text: data.info,
		                    type: "error",
		                    html: true
	                    });
	                }
	            },
	            complete : function(XMLHttpRequest,status){
		            //请求完成后最终执行参数
					if(status=='timeout'){
						//超时,status还有success,error等值的情况
						ajaxTimeoutTest.abort();
	                    swal({
		                    title: "操作失败",
		                    text: "操作超时",
		                    type: "error",
	                    });
					}
				}
	        });
		});
    }

    function checkLicense() {
        var licenseKey = $("#licenseKey");
        if (licenseKey.val().length < 20) {
            swal("Error!", "请输入正确的授权许可编号", "error");
            licenseKey.focus();
            return false;
        } else {
            return true;
        }
    }
</script>

<style type="text/css">
    /*
    隐藏标题
    */
    h1 {
        display: none;
    }
</style>

<div class="row">
    <div class="col-md-9">
		{if $notice}
	        {$notice}
	    {/if}
    </div>
	<div class="col-md-3">
	    <div class="alert alert-success">
	        <div class="text-center">
	            <abbr title="NeWorld 联合管理工具">NeWorld Manager</abbr> 版本： <span class="label label-success">{$version}</span>
	        </div>
	    </div>
  	</div>
    <div class="col-md-12">
        <div class="block block-rounded block-bordered">
            <div class="block-content" style="padding:0">
                <table id="license-list" class="table table-hover small" style="margin-bottom:0">
                    <thead>
                    <tr>
                        <th>产品名称</th>
                        <th>授权许可</th>
                        <th class="hidden-xs">授权时间</th>
                        <th class="text-center">当前</th>
                        <th class="text-center">最新</th>
                        <th class="text-center">操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    {if $product}
                        {foreach $product as $value}
                            <tr id="product_{$value['id']|trim}">
                                <td>{$value['name']|trim}</td>
                                <td>{$value['license']|trim}</td>
                                <td class="hidden-xs">{$value['date']|date_format:"%Y-%m-%d"}</td>
                                <td class="text-center" id="currentversion_{$value['id']|trim}">{$value['version']|trim}</td>
                                <td class="text-center" id="lastversion_{$value['id']|trim}">{$value['lastversion']|trim}</td>
                                <td class="text-center">
                                    {if $value['button'] eq 'activate'}
                                        <div class="btn btn-info btn-xs" onclick="javascript:if (confirm('按下确认，你将跳转至模块设置页面')) location='configaddonmods.php';">
                                            <span class="glyphicon glyphicon-floppy-disk"></span> 启用
                                        </div>
                                    {else}
                                        <div class="btn btn-{if $value['button'] eq 'update'}success{else}warning{/if} btn-xs" data-type="NeWorld" data-action="{$value['button']}" data-license="{$value['license']|trim}" data-name="{$value['name']|trim}" data-id="{$value['id']|trim}" id="button_{$value['id']|trim}">
                                            <span class="glyphicon glyphicon-floppy-save"></span> {if $value['button'] eq 'install'}安装{elseif $value['button'] eq 'reinstall'}重装{elseif $value['button'] eq 'update'}更新{/if}
                                        </div>
                                    {/if}
                                    <div class="btn btn-info btn-xs" data-type="NeWorld" data-action="check" data-license="{$value['license']|trim}" data-name="{$value['name']|trim}" data-id="{$value['id']|trim}">
                                        <span class="glyphicon glyphicon-floppy-saved"></span> 检测
                                    </div>
                                    <div class="btn btn-danger btn-xs" data-type="NeWorld" data-action="delete" data-license="{$value['license']|trim}" data-name="{$value['name']|trim}" data-id="{$value['id']|trim}">
                                        <span class="glyphicon glyphicon-floppy-remove"></span> 删除
                                    </div>
                                </td>
                            </tr>
                        {/foreach}
                    {else}
                        <tr id="message">
                            <td colspan="6" class="text-center">
                                当前还没有添加任何产品
                            </td>
                        </tr>
                    {/if}
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="6">
                            <div class="row">
                                <div class="col-lg-7 col-md-8 col-sm-10">
                                    <div class="input-group">
                                        <input type="text" id="licenseKey" class="form-control" placeholder="请输入授权许可..."><span class="input-group-btn"><button class="btn btn-default" type="submit" id="submitkey">添加授权</button></span>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-12">
        <div class="card">
            <div class="list-group">
                {foreach $noticelist as $value}
                    <a href="{$value['2']|trim}" class="list-group-item">
                        <h4 class="list-group-item-heading">{$value['0']|trim}</h4>
                        <p class="list-group-item-text">
                            {$value['1']|trim}
                        </p>
                    </a>
                {/foreach}
            </div>
        </div>
    </div>
    <div class="col-xs-12 foot text-center">
        <p>
            Copyright &copy NeWorld Cloud Ltd. All Rights Reserved.
        </p>
    </div>
</div>
