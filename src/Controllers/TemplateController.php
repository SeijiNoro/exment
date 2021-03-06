<?php

namespace Exceedone\Exment\Controllers;

use Exceedone\Exment\Services\TemplateInstaller;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\Define;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;
use Illuminate\Http\Request;
use Validator;

class TemplateController extends AdminControllerBase
{
    use InitializeForm;
        
    public function __construct(Request $request){
        $this->setPageInfo(exmtrans("template.header"), exmtrans("template.header"), exmtrans("template.description"));
    }

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        return $this->AdminContent(function (Content $content) {
            $this->exportBox($content);
            $this->importBox($content);
        });
    }

    /**
     * create export box
     */
    protected function exportBox(Content $content){
        $form = new \Encore\Admin\Widgets\Form();
        $form->disablePjax();
        $form->disableReset();
        $form->action(admin_base_path('template/export'));

        $form->description(exmtrans('template.description_export'));
        $form->text('template_name', exmtrans('template.template_name'))->help(exmtrans('common.help_code'));
        $form->text('template_view_name', exmtrans('template.template_view_name'));
        $form->textarea('description', exmtrans('template.form_description'))->rows(3);
        $form->image('thumbnail', exmtrans('template.thumbnail'))->help(exmtrans('template.help.thumbnail'));

        // export target
        $form->checkbox('export_target', exmtrans('template.export_target'))
            ->options(getTransArray(Define::TEMPLATE_EXPORT_TARGET, 'template.export_target_options'))
            ->help(exmtrans('template.help.export_target'))
            ->default(Define::TEMPLATE_EXPORT_TARGET_DEFAULT)
            ;
        
        $form->listbox('target_tables', exmtrans('template.target_tables'))
            ->options(CustomTable::all()->pluck('table_view_name', 'table_name'))
            ->help(exmtrans('template.help.target_tables'))
            ;

        $form->hidden('_token')->default(csrf_token());

        $content->row((new Box(exmtrans('template.header_export'), $form))->style('info'));
    }
    /**
     * create import box
     */
    protected function importBox(Content $content){
        $form = new \Encore\Admin\Widgets\Form();
        $form->disableReset();
        $form->action(admin_base_path('template/import'));

        $form->description(exmtrans('template.description_import'));
        $this->addTemplateTile($form);
        $form->hidden('_token')->default(csrf_token());

        $content->row((new Box(exmtrans('template.header_import'), $form))->style('info'));
    }

    /**
     * export
     */
    public function export(Request $request)
    {
        //validate
        $rules = [
            'template_name' => 'required|max:64|regex:/'.Define::RULES_REGEX_ALPHANUMERIC_UNDER_HYPHEN.'/',
            'template_view_name' => 'required|max:64',
            'thumbnail' => 'nullable|file|mimes:jpeg,gif,png',
            'export_target' => 'required',
        ];

        $validation = Validator::make($request->all(), $rules);

        if ($validation->fails()) {
            return back()->withInput()->withErrors($validation);
        }

        // execute export
        return TemplateInstaller::exportTemplate(
            $request->input('template_name'),
            $request->input('template_view_name'),
            $request->input('description'),
            $request->file('thumbnail'),
            [
                'export_target' => array_filter($request->input('export_target')),
                'target_tables' => array_filter($request->input('target_tables')),
            ]
        );
    }

    /**
     * import
     */
    public function import(Request $request)
    {
        // upload template file and install 
        $this->uploadTemplate($request);

        // install templates selected tiles.
        if ($request->has('template')) {
            TemplateInstaller::installTemplate($request->input('template'));
        }

        admin_toastr(trans('admin.save_succeeded'));
        return back();    
    }

}
