<?php


namespace ArtisanWebworks\ContextLogger\Test\Fixtures;

use Illuminate\Database\Eloquent\Model;

class BazModel extends Model {
  protected $fillable = ['can-recognize', 'bar_model_id'];
}