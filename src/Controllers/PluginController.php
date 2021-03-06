<?php

namespace Exceedone\Exment\Controllers;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Table;
use Encore\Admin\Controllers\ModelForm;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Services\PluginInstaller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use File;
use Validator;

class PluginController extends AdminControllerBase
{
    use ModelForm, AuthorityForm;

    public function __construct(Request $request){
        $this->setPageInfo(exmtrans("plugin.header"), exmtrans("plugin.header"), exmtrans("plugin.description"));  
    }

    /**
     * Index interface.
     *
     * @return Content
     */
    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        return $this->AdminContent(function (Content $content) {
            $content->row(view('exment::plugin.upload'));
            $content->body($this->grid());
        });
    }

    /**
     * Edit interface.
     *
     * @param $id
     * @return Content
     */
    public function edit($id)
    {
        return $this->AdminContent(function (Content $content) use ($id) {
            $content->body($this->form($id)->edit($id));
        });
    }

    /**
     * Create interface.
     *
     * @return Content
     */
    public function create(Request $request)
    {
        return $this->AdminContent(function (Content $content) {
            $content->body($this->form());
        });
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Admin::grid(Plugin::class, function (Grid $grid) {

            $grid->filter(function ($filter) {
                $filter->disableIdFilter();
                $filter->like('uuid', exmtrans("plugin.uuid"));
                $filter->like('plugin_name', exmtrans("plugin.plugin_name"));
            });

            $grid->column('plugin_name', exmtrans("plugin.plugin_name"))->sortable();
            $grid->column('plugin_view_name', exmtrans("plugin.plugin_view_name"))->sortable();
            $grid->column('plugin_type', exmtrans("plugin.plugin_type"))->display(function ($plugin_type) {
                switch ($plugin_type) {
                    case 'page':
                        return '画面';
                    default:
                        return '機能';
                }
            })->sortable();
            $grid->column('author', exmtrans("plugin.author"));
            $grid->column('version', exmtrans("plugin.version"));
            $grid->column('active_flg', exmtrans("plugin.active_flg"))->display(function ($active_flg) {
                return boolval($active_flg) ? exmtrans("common.available_true") : exmtrans("common.available_false");
            });

            $grid->disableCreateButton();
            $grid->disableExport();
            
            $grid->actions(function ($actions) {
                $actions->disableView();
            });
        });
    }

    //Function use to upload file and update or add new record
    protected function store(Request $request)
    {
        //Check file existed in Request
        if ($request->hasfile('fileUpload')) {
            return PluginInstaller::uploadPlugin($request->file('fileUpload'));
        }
        // if not exists, return back and message
        return back()->with('errorMess', exmtrans("plugin.help.errorMess"));
    }

    //Function validate config.json file with field required
    public function checkRuleConfigFile($json)
    {
        $rules = [
            'plugin_name' => 'required',
            'plugin_type' => 'required|in:trigger,page,dashboard,batch',
            'plugin_view_name' => 'required',
            'uuid' => 'required'
        ];

        //If pass validation return true, else return false
        $validator = Validator::make($json, $rules);
        if ($validator->passes()) {
            return true;
        } else {
            return false;
        }
    }

    //Function prepare data to do continue
    protected function prepareData($json, $plugin)
    {
        $plugin->plugin_name = $json['plugin_name'];
        $plugin->plugin_type = $json['plugin_type'];
        $plugin->author = $json['author'] ?? '';
        $plugin->version = $json['version'] ?? '';
        $plugin->uuid = $json['uuid'];
        $plugin->plugin_view_name = $json['plugin_view_name'];
        $plugin->description = $json['description'] ?? '';
        $plugin->active_flg = true;

        return $plugin;
    }

    //Check existed plugin name
    protected function checkPluginNameExisted($name)
    {
        $plugin = DB::table('plugins')
            ->where('plugin_name', '=', $name)
            ->get();
        return count($plugin);
    }

    //Check existed plugin uuid
    protected function checkPluginUUIDExisted($uuid)
    {
        $plugin = DB::table('plugins')
            ->where('uuid', '=', $uuid)
            ->get();
        return count($plugin);
    }

    //Update record if existed both name and uuid
    protected function updateExistedPlugin($plugin)
    {
        $pluginUpdate = DB::table('plugins')
            ->where('plugin_name', '=', $plugin->plugin_name)
            ->where('uuid', '=', $plugin->uuid)
            ->update(['author' => $plugin->author, 'version' => $plugin->version, 'plugin_type' => $plugin->plugin_type, 'description' => $plugin->description, 'plugin_view_name' => $plugin->plugin_view_name]);
        if ($pluginUpdate >= 0) {
            return true;
        }
        return false;
    }

    //Change folder name with plugin name get from config file
    protected function changePluginNameFolder($json, $appPath, $shortPath, $fileNameOnly, $pluginFolder)
    {
        if ($json['plugin_name'] !== $fileNameOnly) {
            // check existed folder then delete
            if (is_dir($pluginFolder)) {
                File::deleteDirectory($pluginFolder);
            }
            rename($shortPath, $pluginFolder);
            return $shortPath = $appPath . '/plugins/' . $json['plugin_name'];
        }
    }

    //Delete record from database (one or multi records)
    protected function destroy($id)
    {
        $this->deleteFolder($id);
        if ($this->form()->destroy($id)) {
            return response()->json([
                'status' => true,
                'message' => trans('admin.delete_succeeded'),
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => trans('admin.delete_failed'),
            ]);
        }
    }

    //Delete one or multi folder corresponds to the plugins
    protected function deleteFolder($id)
    {
        $arrPlugin = array();
        $appPath = app_path();
        if (strpos($id, ',') !== false) {
            $arrPlugin = explode(',', $id);
            foreach ($arrPlugin as $item) {
                $plugin = DB::table('plugins')
                    ->where('id', '=', $item)
                    ->first();
                $pluginFolder = $appPath . '/plugins/' . strtolower(preg_replace('/\s+/', '', $plugin->plugin_name));
                if (File::isDirectory($pluginFolder)) {
                    File::deleteDirectory($pluginFolder);
                }
            }
        } else {
            $plugin = DB::table('plugins')
                ->where('id', '=', $id)
                ->first();
            $pluginFolder = $appPath . '/plugins/' . strtolower(preg_replace('/\s+/', '', $plugin->plugin_name));
            if (File::isDirectory($pluginFolder)) {
                File::deleteDirectory($pluginFolder);
            }
        }
    }

    //Check request when edit record to delete null values in event_triggers
    protected function update(Request $request, $id)
    {
        if(isset($request->get('options')['event_triggers']) === true){
            $event_triggers = $request->get('options')['event_triggers'];
            $options = $request->get('options');
            $event_triggers = array_filter($event_triggers, 'strlen');
            $options['event_triggers'] = $event_triggers;
            $request->merge(['options' => $options]);
        }
        return $this->form()->update($id);
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($id = null)
    {
        return Admin::form(Plugin::class, function (Form $form) use ($id) {
            $form->display('uuid', exmtrans("plugin.uuid"));
            $form->display('plugin_name', exmtrans("plugin.plugin_name"));
            $form->display('plugin_view_name', exmtrans("plugin.plugin_view_name"));
            $form->display('author', exmtrans("plugin.author"));
            $form->display('version', exmtrans("plugin.version"));
            $form->switch('active_flg', exmtrans("plugin.active_flg"));
            $plugin_type = Plugin::getFieldById($id, 'plugin_type');
            $form->embeds('options', exmtrans("plugin.options.header"), function ($form) use ($plugin_type) {
                if ($plugin_type == 'trigger') {
                    $form->select('target_tables', exmtrans("plugin.options.target_tables"))->options(function ($target_tables) {
                        $options = CustomTable::all()->pluck('table_view_name', 'id')->toArray();
                        return $options;
                    })->help(exmtrans("plugin.help.target_tables"));
                    $form->multipleSelect('event_triggers', exmtrans("plugin.options.event_triggers"))->options(function ($event_triggers) {
                        return getTransArray(Define::PLUGIN_EVENT_TRIGGER, "plugin.options.event_trigger_options");
                    })->help(exmtrans("plugin.help.event_triggers"));
                } else {
                    // Plugin_type = 'page'
                    $form->text('uri', exmtrans("plugin.options.uri"));
                }
                $form->text('label', exmtrans("plugin.options.label"));
                $form->icon('icon', exmtrans("plugin.options.icon"))->help(exmtrans("plugin.help.icon"));
                $form->text('button_class', exmtrans("plugin.options.button_class"))->help(exmtrans("plugin.help.button_class"));
            });

            // Authority setting --------------------------------------------------
            $this->addAuthorityForm($form, Define::AUTHORITY_TYPE_PLUGIN);

            $form->disableReset();
        });
    }
}
