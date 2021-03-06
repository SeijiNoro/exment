<?php

namespace Exceedone\Exment\Controllers;

use Validator;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Services\TemplateInstaller;
use Encore\Admin\Widgets\Form as WidgetForm;
use Encore\Admin\Facades\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

trait InitializeForm
{
    protected function getInitializeForm($add_template = false)
    {
        $form = new WidgetForm(System::get_system_values());
        $form->disableReset();
        
        $form->header(exmtrans('system.header'))->hr();
        $form->text('site_name', exmtrans("system.site_name"))
            ->help(exmtrans("system.help.site_name"));

        $form->text('site_name_short', exmtrans("system.site_name_short"))
            ->help(exmtrans("system.help.site_name_short"));
            
        $form->image('site_logo', exmtrans("system.site_logo"))
           ->help(exmtrans("system.help.site_logo"))
           ;
        $form->image('site_logo_mini', exmtrans("system.site_logo_mini"))
           ->help(exmtrans("system.help.site_logo_mini"))
           ;

        $form->select('site_skin', exmtrans("system.site_skin"))
           ->options(getTransArray(Define::SYSTEM_SKIN, "system.site_skin_options"))
           ->help(exmtrans("system.help.site_skin"));

        $form->select('site_layout', exmtrans("system.site_layout"))
            ->options(getTransArray(array_keys(Define::SYSTEM_LAYOUT), "system.site_layout_options"))
            ->help(exmtrans("system.help.site_layout"));

        $form->switchbool('authority_available', exmtrans("system.authority_available"))
            ->help(exmtrans("system.help.authority_available"));

        $form->switchbool('organization_available', exmtrans("system.organization_available"))
            ->help(exmtrans("system.help.organization_available"));

        $form->email('system_mail_from', exmtrans("system.system_mail_from"))
            ->help(exmtrans("system.help.system_mail_from"));

        // template list
        if($add_template){
            $this->addTemplateTile($form);
        }

        return $form;
    }

    protected function postInitializeForm(Request $request, $validateUser = false)
    {
        if ($validateUser) {
            $rules = [
                'user_code' => 'required|max:32|regex:/'.Define::RULES_REGEX_ALPHANUMERIC_UNDER_HYPHEN.'/',
                'user_name' => 'required|max:32',
                'email' => 'required|email',
                'password' => get_password_rule(true),
            ];

            $validation = Validator::make($request->all(), $rules);

            if ($validation->fails()) {
                return back()->withInput()->withErrors($validation);
            }
        }

        $inputs = $request->all(array_keys(Define::SYSTEM_SETTING_ID_VALUE));
        array_forget($inputs, 'initialized');
       
        // set system_key and value
        foreach ($inputs as $k => $input) {
            System::{$k}($input);
        }

        // upload zip file
        $this->uploadTemplate($request);

        // install template
        if ($request->has('template')) {
            TemplateInstaller::installTemplate($request->input('template'));
        }

        return true;
    }
    
    /**
     * get system template list
     */
    protected function getTemplates()
    {
        $templates_path = app_path("Templates");
        $paths = File::glob("$templates_path/*/config.json");

        $templates = [];
        foreach ($paths as $path) {
            try {
                $json = json_decode(File::get($path));
                array_push($templates, $json);
            } catch (Exception $exception) {
                //TODO:error handling
            }
        }

        return collect($templates);
    }
     
    protected function addTemplateTile($form){
        // template list
        $form->tile('template', exmtrans("system.template"))
            ->options(function ($template) {
                $array = TemplateInstaller::getTemplates();
                if (is_null($array)) {
                    return [];
                }
                $options = [];
                foreach ($array as $a) {
                    // get thumbnail_path
                    if(isset($a['thumbnail_fullpath'])){
                        $thumbnail_path = $a['thumbnail_fullpath'];
                    }else{
                        $thumbnail_path = base_path() . '/vendor/exceedone/exment/templates/noimage.png';
                    }
                    array_push($options, [
                        'id' => array_get($a, 'template_name'),
                        'title' => array_get($a, 'template_view_name'),
                        'description' => array_get($a, 'description'),
                        'author' => array_get($a, 'author'),
                        'thumbnail' => 'data:image/png;base64,'.base64_encode(file_get_contents($thumbnail_path))
                    ]);
                }

                return $options;
            })
            ->help(exmtrans("system.help.template"))
            ;

        $form->file('upload_template', exmtrans('template.upload_template'))->rules('mimes:zip|nullable')->help(exmtrans('template.help.upload_template'));
    }

    /**
     * Upload Template
     */
    protected function uploadTemplate(Request $request){
        
        // upload zip file
        $upload_template = null;
        if($request->has('upload_template')){
            // get upload file
            $file = $request->file('upload_template');
            $upload_template = TemplateInstaller::uploadTemplate($file);
            TemplateInstaller::installTemplate($upload_template);
        }
    }
}
