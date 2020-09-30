<?php


namespace ArtisanWebworks\ContextLogger;


use Illuminate\Support\ServiceProvider;

class ContextLoggerServiceProvider extends ServiceProvider {

  public function register() {
    $this->mergeConfigFrom(
      __DIR__ . '/config/context-logger.php',
      'auto-crud'
    );
  }

  public function boot() {
    $this->publishes(
      [
        __DIR__ . '/config/context-logger.php' => config_path('context-logger.php')
      ]
    );
  }

}
