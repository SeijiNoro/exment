<?php

namespace Exceedone\Exment\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Relations\Relation;
use Exceedone\Exment\Model;
use Exceedone\Exment\Model\CustomTable;

class Morph
{
    public function handle(Request $request, \Closure $next)
    {
        static::defineMorphMap();
        return $next($request);
    }

    /**
     * define morph map. this called from command.
     * 
     */
    public static function defineMorphMap(){
        // morphMap
        if(Schema::hasTable(CustomTable::getTableName())){
            $table_names = CustomTable::all(['table_name'])->pluck('table_name');
            $morphMaps = [
                "authorities" => Model\Authority::class,
                "table" => CustomTable::class
            ];
            foreach ($table_names as $table_name)
            {
                // morphmap
                $morphMaps[$table_name] = ltrim(getModelName($table_name, true), "\\");
            }
            Relation::morphMap($morphMaps);
        }
    }
}
