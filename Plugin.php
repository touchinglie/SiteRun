<?php

namespace TypechoPlugin\SiteRun;

use Typecho\Date;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Utils\Helper;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 网站页脚运行时间及后台入口优化
 *
 * 在首页页脚右侧显示"网站已运行：x年x天x小时x分"，可在插件设置中配置开始时间。
 *
 * @package SiteRun
 * @author Dow
 * @version 1.0.0
 * @since 1.3.0
 * @link https://www.dowblog.top/
 */
class Plugin implements PluginInterface
{
    /**
     * 激活插件方法，注册页脚 Hook
     */
    public static function activate()
    {
        // 注册 Widget\Archive 的 footer Hook，用于在主题页脚输出运行时间
        \Typecho\Plugin::factory('Widget\Archive')->footer = __CLASS__ . '::render';
    }

    /**
     * 禁用插件方法。禁用时 Typecho 会自动移除本插件注册的 Hook（handles），无需手动清理。
     */
    public static function deactivate()
    {
    }

    /**
     * 获取插件配置面板
     *
     * @param Form $form 配置面板
     */
    public static function config(Form $form)
    {
        /** 博客开始时间 */
        $startTime = new Text(
            'startTime',
            null,
            '2020-01-01 00:00:00',
            _t('博客开始时间'),
            _t('请填写博客开始运行的时间，格式如：2020-01-01 00:00:00')
        );
        $form->addInput($startTime);
    }

    /**
     * 个人用户的配置面板
     *
     * @param Form $form
     */
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 页脚渲染入口
     *
     * @param \Widget\Archive $archive 当前归档对象
     */
    public static function render($archive)
    {
        // （可选）仅当在首页时输出
        // if (empty($archive) || !$archive->is('index')) {
        //     return;
        // }

        try {
            // 获取系统配置
            $options = Helper::options();
            $pluginOptions = Options::alloc()->plugin('SiteRun');
        } catch (\Exception $e) {
            // 插件配置未保存或不存在时静默退出
            return;
        }

        // 获取网站设置的域名地址
        $siteUrl = $options->siteUrl;

        $startTime = $pluginOptions->startTime;
        if (empty($startTime)) {
            return;
        }

        // 根据后台设置的时区偏移（秒）构造如 +08:00 的时区后缀
        // 参照 Typecho 原生 Widget\Contents\EditTrait 的换算逻辑，
        // 使开始时间在“后台设置时区”下解析，避免服务器时区不一致产生的偏差
        $timezoneOffset = (int) $options->timezone;
        $timezoneSymbol = $timezoneOffset >= 0 ? '+' : '-';
        $timezoneAbs = abs($timezoneOffset);
        $timezone = $timezoneSymbol
            . str_pad((int) ($timezoneAbs / 3600), 2, '0', STR_PAD_LEFT)
            . ':' . str_pad((int) (($timezoneAbs % 3600) / 60), 2, '0', STR_PAD_LEFT);

        // 将用户输入的时间改为 ISO 格式并附加时区，得到标准 UTC 时间戳
        $isoTime = str_replace(' ', 'T', trim($startTime));
        $start = strtotime($isoTime . $timezone);

        // 当前时间取 UTC 时间戳（time() / Date::time() 均与服务器时区无关）
        $now = Date::time();
        if (false === $start || $start > $now) {
            return;
        }

        $diff = $now - $start;

        // 计算运行时长：x年x天x小时x分（一年按 365.2425 天计）
        $years    = intdiv($diff, 31556926); 
        $remain   = $diff % (31556926);
        $days     = intdiv($remain, 86400);
        $remain   = $remain % 86400;
        $hours    = intdiv($remain, 3600);
        $remain   = $remain % 3600;
        $minutes  = intdiv($remain, 60);

        // 可在此自行修改显示样式及后台入口，插件适配的是 Classic 22 的typecho官方新版主题
        echo '<div class="site-footer container-fluid"><div class="d-flex justify-content-between" style="vertical-align:inherit;"><ul class="list-inline text-muted">'
            . _t('<a href="%s">后台管理</a>', $siteUrl . 'admin/')
            . '</ul><ul class="list-inline text-muted"><li class="site-run-time">'
            . _t('网站已运行：%s年%s天%s小时%s分', $years, $days, $hours, $minutes)
            . '</li></ul></div></div>';
    }
}