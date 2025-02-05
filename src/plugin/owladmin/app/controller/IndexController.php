<?php

namespace plugin\owladmin\app\controller;

use support\Request;
use support\Response;
use plugin\owladmin\app\Admin;
use plugin\owladmin\app\model\Plugin;
use plugin\owladmin\app\service\AdminPageService;

class IndexController extends AdminController
{
    public function menus(): Response
    {
        return $this->response()->success(Admin::menu()->all());
    }

    public function noContentResponse(): Response
    {
        return $this->response()->successMessage();
    }

    public function settings(): Response
    {
        $prefix = '';
        $localeOptions = Admin::config('admin.layout.locale_options') ?? [
            'en'    => 'English',
            'zh_CN' => '简体中文',
        ];
        return $this->response()->success([
            'nav'      => Admin::getNav(),
            'assets'   => Admin::getAssets(),
            'app_name' => Admin::config('admin.name'),
            'locale'   => settings()->get('admin_locale', config('plugin.owladmin.translation.locale')), // webman
            'layout'   => Admin::config('admin.layout'),
            'logo'     => url(Admin::config('admin.logo')),

            'login_captcha'          => Admin::config('admin.auth.login_captcha'),
            'locale_options'         => map2options($localeOptions),
            'show_development_tools' => Admin::config('admin.show_development_tools'),
            'system_theme_setting'   => Admin::setting()->get($prefix . 'system_theme_setting'),
            'enabled_extensions'     => Plugin::query()->where('is_enabled', 1)->pluck('name')?->toArray(),
        ]);
    }

    /**
     * 保存设置项
     *
     * @param Request $request
     *
     * @return Response
     */
    public function saveSettings(Request $request): Response
    {
        $data = $request->all();
        Admin::setting()->setMany($data);
        return $this->response()->successMessage();
    }

    /**
     * 下载导出文件
     *
     * @param Request $request
     *
     * @return Response
     */
    public function downloadExport(Request $request):Response
    {
        return response()->download(base_path($request->input('path')));
    }

    /**
     * 图标搜索
     *
     * @return Response
     */
    public function iconifySearch(): Response
    {
        $query = request()->input('query', 'home');

        $icons = file_get_contents(owl_admin_path('/app/support/iconify.json'));
        $icons = json_decode($icons, true);

        $items = [];
        foreach ($icons as $item) {
            if (str_contains($item, $query)) {
                $items[] = ['icon' => $item];
            }
            if (count($items) > 999) {
                break;
            }
        }

        $total = count($items);

        return $this->response()->success(compact('items', 'total'));
    }

    /**
     * 获取页面结构
     *
     * @return Response
     */
    public function pageSchema(): Response
    {
        return $this->response()->success(AdminPageService::make()->get(request()->get('sign'))); // webman
    }
}
