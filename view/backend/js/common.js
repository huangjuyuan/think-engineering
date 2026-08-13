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
    // ===== 登录校验：未登录则跳转登录页 =====
    var token = localStorage.getItem('loginToken') || '';
    if (!token) {
        window.location.href = '/view/backend/user/login.html';
        return;
    }

    // 依次加载公共组件到占位容器
    $('#app-header').load('../common/header.html');
    $('#app-footer').load('../common/footer.html');
    // 侧边栏加载完成后，从接口拉取菜单并动态渲染
    $('#app-sidebar').load('../common/sidebar.html', function () {
        loadMenu();
    });
});

/**
 * 退出登录：清除本地登录态并跳转到登录页
 */
function doLogout() {
    localStorage.removeItem('loginToken');
    localStorage.removeItem('loginRole');
    localStorage.removeItem('loginUser');
    window.location.href = '/view/backend/user/login.html';
}

/**
 * 渲染单个菜单节点（递归，支持多级）
 * @param {Object} node 菜单节点：{id, pid, title, icon, url, type, children}
 * @returns {string} li 的 HTML
 */
function renderMenuItem(node) {
    var hasChildren = node.children && node.children.length > 0;
    var icon = node.icon ? '<i class="' + node.icon + ' menu-icon"></i>' : '<i class="icon-doc menu-icon"></i>';
    var text = '<span class="nav-text">' + (node.title || '') + '</span>';

    // 目录（可展开）或含子节点的菜单
    if (hasChildren || node.type == 1) {
        // 子菜单 html
        var sub = '';
        if (hasChildren) {
            sub = '<ul aria-expanded="false">';
            node.children.forEach(function (child) {
                sub += renderMenuItem(child);
            });
            sub += '</ul>';
        }
        return '<li>' +
            '<a href="javascript:void(0)" aria-expanded="false" class="has-arrow">' + icon + text + '</a>' +
            sub +
        '</li>';
    }

    // 普通菜单（可点击链接）
    var href = node.url || 'javascript:void(0)';
    return '<li><a href="' + href + '" aria-expanded="false">' + icon + text + '</a></li>';
}

/**
 * 拉取菜单树并渲染到侧边栏
 */
function loadMenu() {
    var menuEl = $('#menu');
    if (!menuEl.length) return;

    // 携带 JWT token 请求菜单接口，后端从 token 解析角色（RBAC）
    var token = localStorage.getItem('loginToken') || '';
    $.ajax({
        url: '/backend/menu/list',
        type: 'GET',
        headers: { 'Authorization': 'Bearer ' + token },
        dataType: 'json',
        success: function (res) {
            if (res.code === 0 && res.data) {
                var html = '';
                res.data.forEach(function (node) {
                    html += renderMenuItem(node);
                });
                menuEl.html(html);
            }

            // 重新初始化 metismenu（侧边栏展开/收缩）
            $("#menu").metisMenu();
            $('.nk-nav-scroll').slimscroll({
                position: "right",
                size: "5px",
                height: "100%",
                color: "transparent"
            });
        },
        error: function () {
            // 接口失败时也初始化，避免菜单不可用
            $("#menu").metisMenu();
        }
    });
}
