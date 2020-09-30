<?php

namespace ArtisanWebworks\ContextLogger\Test\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FooModel extends Model {

  protected $fillable = ['name', 'user_id'];
  protected $casts = ['user_id' => 'int'];
  protected array $rules = ['name' => 'string|required|min:3'];
  protected array $messages = [
    'min' => ':attribute must be at least :min characters',
    //      'same'    => 'The :attribute and :other must match.',
    //      'size'    => 'The :attribute must be exactly :size.',
    //      'between' => 'The :attribute must be between :min - :max.',
    //      'in'      => 'The :attribute must be one of the following types: :values',
  ];

  public function barModels(): HasMany {
    return $this->hasMany('ArtisanWebworks\ContextLogger\Test\Fixtures\BarModel');
  }

}