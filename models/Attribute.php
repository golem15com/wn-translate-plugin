<?php

namespace Golem15\Translate\Models;

use Model;

/**
 * Attribute Model
 */
class Attribute extends Model
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'winter_translate_attributes';

    /**
     * @var array Mass-assignable attributes (UTIL-02 / TRANSLATE-002).
     */
    protected $fillable = [
        'locale',
        'model_type',
        'model_id',
        'attribute_data',
    ];

    /**
     * @var array Guarded fields — reset because we use $fillable instead.
     */
    protected $guarded = ['*'];

    public $morphTo = [
        'model' => []
    ];
}
