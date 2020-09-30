<?php


namespace ArtisanWebworks\ContextLogger;


interface ILogContextData {

  /**
   * Exposes data about the implementing object that is useful
   * for including in log events where the implementor has
   * been specified as part of the logging context.
   *
   * @return array - key-value log context data
   */
  public function getLogContextData();

}