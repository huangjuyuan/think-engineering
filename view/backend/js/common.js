/**
 * 后台公共组件加载逻辑
 *
 * 通过 jQuery .load() 加载公共 Header/Footer/Sidebar，
 * 并在 Sidebar 加载完成后重新初始化 metismenu（侧边栏展开/收缩）与 slimscroll（滚动）。
 *
 * 使用前提：
 *   1. 本文件必须在 jquery / common.min.js 之后引入。
 *   2. 页面需包含占位容器：#app-header / #app-sidebar / #app-footer。
 *   3. 路径约定：页面位于 view/backend 的一级子目录（如 goods/、user/），
 *      common 组件路径为 ../common/*.html。若页面位置变化，请同步调整下方路径。
 */
$(function () {
    // 依次加载公共组件到占位容器
    $('#app-header').load('../common/header.html');
    $('#app-footer').load('../common/footer.html');
    // 侧边栏加载完成后，重新初始化 metismenu（侧边栏展开/收缩）
    $('#app-sidebar').load('../common/sidebar.html', function () {
        // 重新绑定侧边栏菜单展开/收缩
        $("#menu").metisMenu();
        $('.nk-nav-scroll').slimscroll({
            position: "right",
            size: "5px",
            height: "100%",
            color: "transparent"
        });

        // ======= 登录逻辑（请求假接口 /backend/user/login） =======
        window.__sidebarLoginReady = true;

        // 登录提交
        window.doLogin = function () {
            var username = $('#loginUsername').val();
            var password = $('#loginPassword').val();
            if (!username || !password) {
                $('#loginMsg').text('用户名和密码不能为空');
                return;
            }
            $('#loginMsg').text('');
            $.post('/backend/user/login', { username: username, password: password }, function (res) {
                if (res.code === 0) {
                    // 登录成功：写入本地标记并刷新状态
                    localStorage.setItem('loginUser', res.data.user.nickname || res.data.user.username);
                    localStorage.setItem('loginToken', res.data.token);
                    $('#loginModal').modal('hide');
                    renderLoginState();
                } else {
                    $('#loginMsg').text(res.msg || '登录失败');
                }
            }, 'json').fail(function () {
                $('#loginMsg').text('请求失败，请检查接口地址');
            });
        };

        // 刷新登录状态显示
        function renderLoginState() {
            var user = localStorage.getItem('loginUser');
            if (user) {
                $('#loginUser').text(user);
                $('#btnLogin').html('<i class="icon-logout"></i> 退出').attr('onclick', 'logout()');
            } else {
                $('#loginUser').text('未登录');
                $('#btnLogin').html('<i class="icon-key"></i> 登录').attr('onclick', 'openLoginModal()');
            }
        }
        window.renderLoginState = renderLoginState;

        // 退出登录
        window.logout = function () {
            localStorage.removeItem('loginUser');
            localStorage.removeItem('loginToken');
            renderLoginState();
        };

        // 初始渲染登录状态
        renderLoginState();
    });
});
