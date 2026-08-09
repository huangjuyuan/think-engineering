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
    });
});
