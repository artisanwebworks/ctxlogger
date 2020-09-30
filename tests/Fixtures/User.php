<?php

namespace ArtisanWebworks\ContextLogger\Test\Fixtures;

use ArtisanWebworks\ContextLogger\Test\Fixtures\FooModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model {

  protected $fillable = [
    'username',
  ];

  public function foomodels(): HasMany {
    return $this->hasMany(FooModel::class);
  }

}