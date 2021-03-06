<?php

namespace Exceedone\Exment\Model;


class CustomRelation extends ModelBase
{
    public function parent_custom_table(){
        return $this->belongsTo(CustomTable::class, 'parent_custom_table_id');
    }

    public function child_custom_table(){
        return $this->belongsTo(CustomTable::class, 'child_custom_table_id');
    }
}
