<?php
/**
 * User: dengxiaowu@innifo.cn
 * Date: 2018/08/16
 * Time: 17:02
 */

function get_action_name($action)
{
    $map = array(
        'select_header' => '选股头部信息',
        'order_serviceisbuy' => '是否购买服务',
        'activity_info' => '活动信息',
        'select_netvalue' => '净收益曲线',
        'select_start' => '选股股票池',
        'select_issubscribe' => '股票池订阅',
        'misc_index' => '杂项',
        'user_islogin' => '是否登录',
        'newindex_getsmartrecord' => '策略选股模块',
        'newindex_gettodaythemelist' => '今天热门板块',
        'market_discoverer' => '发现页',
        'market_getallsign' => '实时信号',
        'newindex_getuserstocks' => '自选列表',
        'market_onlineindex' => '指数分布',
        'market_index' => '行情首页总接口',
        'nstock_reddot' => '资讯和实时信号红点',
        'news_query' => '快讯列表',
        'news_getlastestnum' => '快讯更新',
        'user_get' => '获取用户信息',
        'order_getuserservices' => '用户服务',
        'user_checkbind' => '检查绑定',
        'theme_getinflowlist' => '主力净流入',
        'newindex_getselectthemelist' => '板块主题快速涨跌',
        'activitylike_add' => '点赞活动添加',
        'miniapp_sharenews' => '分享快讯',
        'stocks_index' => '股票个股首页',
        'theme_getstocktheme' => '板块股票列表',
        'market_isfavor' => '是否自选',
        'stocks_getdkmode' => '多空模式',
        'market_stocklist' => '股票涨跌幅列表',
        'miniapp_getunioninfo' => '小程序快捷登录',
        'miniapp_getwxphone' => '小程序登录',
        'user_savewxinfo' => '保存微信信息',
        'newindex_getgoodcompany' => '好公司',
        'newindex_getwindk' => '精选D点',
        'user_infos' => '用户信息',
        'theme_getthemelist' => '板块列表',
        'newindex_getbannerlist' => 'banner列表',
        'getmsgcenter' => '消息列表',
        'market_onlinescopecount' => '大盘涨幅分布',
        'stocks_getdkstock' => '多空模式的个股信息',
        'market_stocktoplist' => '榜单股票涨跌幅列表',
    );

    return isset($map[$action]) ? $map[$action] : '';
}