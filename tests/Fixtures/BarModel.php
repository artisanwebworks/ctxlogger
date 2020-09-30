<?php


namespace ArtisanWebworks\ContextLogger\Test\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarModel extends Model {
  protected $fillable = ['level', 'foo_model_id'];

  public function bazModels(): HasMany {
    return $this->hasMany(BazModel::class);
  }

}