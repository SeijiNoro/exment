<?php

namespace Exceedone\Exment\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Encore\Admin;
use Encore\Admin\Form;
use Encore\Admin\Facades\Admin as Ad;
use Exceedone\Exment\Form\Field;
use Exceedone\Exment\Controllers;

/**
 * 初期設定に関するMiddleware
 */
class Bootstrap
{
    public function handle(Request $request, \Closure $next)
    {
        $map = [
            'number'        => Field\Number::class,
            'image'        => Field\Image::class,
            'display'        => Field\Display::class,
            'link'           => Field\Link::class,
            'header'           => Field\Header::class,
            'description'           => Field\Description::class,
            'switchbool'          => Field\SwitchBoolField::class,
            'pivotMultiSelect'          => Field\PivotMultiSelect::class,
            'checkboxone'          => Field\Checkboxone::class,
            'tile'          => Field\Tile::class,
            'hasManyTable'           => Field\HasManyTable::class,
            'relationTable'          => Field\RelationTable::class,
            'tableitem'          => Field\RelationTableItem::class,
            'nestedEmbeds'          => Field\NestedEmbeds::class,
            'valueModal'          => Field\ValueModal::class,
        ];
        foreach ($map as $abstract => $class) {
            Form::extend($abstract, $class);
        }

        Ad::navbar(function (\Encore\Admin\Widgets\Navbar $navbar) {
            $navbar->left(Controllers\SearchController::renderSearchHeader());
        });
        Ad::js(asset('lib/js/jquery-ui.min.js'));
        Ad::css(asset('lib/css/jquery-ui.min.css'));

        Ad::js(asset('lib/js/bignumber.min.js'));

        Ad::css(asset('vendor/exment/css/common.css'));
        Ad::js(asset('vendor/exment/js/common.js'));
        Ad::js(asset('vendor/exment/js/numberformat.js'));
        
        // add admin_base_path
        $prefix = config('admin.route.prefix') ?? '';
        $script = <<<EOT
$('body').append($('<input/>', {
    'type':'hidden',
    'id': 'admin_base_path',
    'value': '$prefix'
}));
EOT;
        Ad::script($script);
    
        return $next($request);
    }
}
