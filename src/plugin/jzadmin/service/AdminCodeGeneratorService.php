<?php

namespace plugin\jzadmin\service;

use plugin\jzadmin\Admin;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Builder;
use plugin\jzadmin\model\AdminCodeGenerator;

/**
 * @method AdminCodeGenerator getModel()
 * @method AdminCodeGenerator|Builder query()
 */
class AdminCodeGeneratorService extends AdminService
{
    protected string $modelName = AdminCodeGenerator::class;

    public function listQuery()
    {
        $keyword = request()->input('keyword');// webman

        return parent::listQuery()->when($keyword, function ($query) use ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('table_name', 'like', "%{$keyword}%")->orWhere('title', 'like', "%{$keyword}%");
            });
        });
    }

    public function store($data): bool
    {
        amis_abort_if($this->query()->where('table_name', $data['table_name'])->exists(), admin_trans('admin.code_generators.exists_table'));

        return parent::store($this->filterData($data));
    }

    public function update($primaryKey, $data): bool
    {
        $exists = $this->query()
            ->where('table_name', $data['table_name'])
            ->where($this->primaryKey(), '<>', $primaryKey)
            ->exists();

        amis_abort_if($exists, admin_trans('admin.code_generators.exists_table'));

        return parent::update($primaryKey, $this->filterData($data));
    }

    public function filterData($data)
    {
        admin_abort_if(!data_get($data, 'columns'), admin_trans('admin.required', ['attribute' => admin_trans('admin.code_generators.column_info')]));

        admin_abort_if(
            collect($data['columns'])->pluck('name')->unique()->count() != count($data['columns']),
            admin_trans('admin.code_generators.duplicate_column')
        );

        $data['columns'] = collect($data['columns'])
            ->map(fn($item) => Arr::except($item, ['component_options']))
            ->toArray();

        if (in_array('need_create_table', $data['needs'])) {
            $data['needs'][] = 'need_database_migration';
            $data['needs']   = array_unique($data['needs']);
        }

        $data['page_info']['list_display_created_at'] = $data['list_display_created_at'] ?? 1;
        $data['page_info']['list_display_updated_at'] = $data['list_display_updated_at'] ?? 1;

        return Arr::except($data, ['table_info', 'table_primary_keys']);
    }

    /**
     * 获取命名空间
     *
     * @param $name
     * @param $app
     *
     * @return string
     */
    public function getNamespace($name, $app = null): string
    {
        $namespace = collect(explode('\\', Admin::config('admin.route.namespace')));

        $namespace->pop();

        if ($app && !Admin::currentModule()) {
            $namespace->pop();
        }

        return $namespace->push($name)->implode('/') . '/';
    }

    public function getDefaultPath()
    {
        return [
            'label' => Admin::currentModule() ?: admin_trans('admin.code_generators.save_path_dir'),
            'value' => [
                'controller_path' => $this->getNamespace('controller'),
                'service_path'    => $this->getNamespace('service', 1),
                'model_path'      => $this->getNamespace('model', 1),
            ],
        ];
    }

    public function getComponentOptions()
    {
        return collect(get_class_methods(amis()))
            ->filter(fn($item) => $item != 'make')
            ->map(function ($item) {
                $renderer = new \ReflectionClass('\\plugin\\jzadmin\\renderer\\' . $item);
                $_doc     = $renderer->getDocComment();
                $_doc     = preg_replace("/[^\x{4e00}-\x{9fa5}]/u", "", $_doc);
                $_doc     = $_doc ? trim(str_replace('文档', '', $_doc)) : '';
                $label    = $_doc ? $item . ' - ' . $_doc : $item;

                return [
                    'label' => $label,
                    'value' => $item,
                ];
            })
            ->values()
            ->toArray();
    }
}
