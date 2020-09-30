<?php


namespace ArtisanWebworks\ContextLogger\Test;

use Illuminate\Support\Facades\Log;

//use function ArtisanWebworks\ContextLogger\_info;

class HelperFunctionsTest extends TestBase {

  /** @test */
  public function info_helper_invokes_log_facade() {
    Log::shouldReceive('info')->once();
    _info('hello');
  }

}