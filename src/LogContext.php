<?php

namespace ArtisanWebworks\ContextLogger;

use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

define('DEFAULT_DUMP_THRESHOLD', 128);

/**
 * Tracks log context globally for a request; allows call site to push
 * and pop sub-contexts, to avoid having to specify the full logging
 * context at each call site.
 *
 * Returns context data as flat array, with each key prefixed by context
 * name.
 */
class LogContext {

  private static array $globalContextStack = [];

  /**
   * Push a new subcontext onto logging context stack, identified by
   * subcontext name.
   *
   * Context values greater than MAX_VALUE_LENGTH, will be dumped
   * to storage as a file and referenced in the log context as a pointer,
   * of the form "dump:ID".
   *
   * @param string $name - subcontext identifier, which is appended to
   *  subcontext values in the final (flat) log context object. Referenced
   *  when popping a subcontext.
   *
   * @param mixed $contextObj - a key value array of contextual data, an Exception,
   *  or an object exposing ILogContextData.
   */
  public static function pushSubContext(string $name, $contextObj) {

    // Convert various context object types to a key-value array
    if (!($data = static::contextObjectToArray($contextObj))) {
      return;
    }

    $formattedData = self::formatContextData($data, $name);

    static::$globalContextStack[$name] = $formattedData;
  }

  /**
   * Remove a subcontext that is no longer relevant
   * @param string $name subcontext name
   */
  public static function popSubContext(string $name) {
    unset(static::$globalContextStack[$name]);
  }

  /*
   * Get the current log context, with optional caller context data
   * appended on.
   */
  public static function getContext($withAppended = []) {

    $flattenedContext = call_user_func_array(
      'array_merge',
      static::$globalContextStack
    );

    return array_merge(['t' => time()], $flattenedContext, $withAppended);
  }

  /**
   * Dumps bulky log context values to file on disk, referenced by a
   * pointer in the log statement of the form "dump/{CRC32}.
   *
   * The actual file path is storage/app/dump/{CRC32}.htm
   *
   * We store as htm because a common use case is an html formatted
   * exception trace. Other values will be plain text, also convenient
   * to view in browser.
   *
   * @param $str
   * @return string - dump file id
   */
  public static function dumpValueToFile($str) {
    $outputPath = "dump/" . crc32($str);
    Storage::put($outputPath . '.htm', $str);

    // We use the storage sub-path as the full dump 'id'
    return $outputPath;
  }

  /**
   * Derive log context data array from Exception
   *
   * @param Exception $e
   * @return array - of form ['msg' => ..., 'details' => string dump]
   */
  public static function contextDataFromException(Exception $e) {

    $data = ['err_msg' => $e->getMessage()];

    // Try to render as html string the current app context
    // is capable; otherwise convert to plain text string.
    $handler = null;
    try {
      $handler = app()->make(
        ExceptionHandler::class
      );
    } catch (BindingResolutionException $e) {
    }

    // Format the exception trace as html or plain text.
    $makeFormattedTrace = function (Exception $e) use ($handler) {
      if (
        $handler &&
        method_exists($handler, 'renderExceptionAsHtml')
      ) {
        return $handler->renderExceptionAsHtml($e);
      }
      else {
        return $e->__toString();
      }
    };

    $data['trace'] = $makeFormattedTrace($e);

    // We'll record the previous exception too, if any.
    $previous = $e->getPrevious();
    if ($previous) {
      $data['inner_err_msg'] = $previous->getMessage();
      $data['inner_trace'] = $makeFormattedTrace($previous);
    }

    return $data;
  }

  /*
   * Get qualified method name of log call site, eg
   * 'FooClass::barMethod'.
   *
   * Depends on call site being exactly 3 frames higher on call stack;
   * expected call stack on invocation:
   *
   *  0: this call
   *  1: LogContext::emitEntry()
   *  2: _[info|warn|error]()
   *  3: FooClass::barMethod()   <---- call site
   */
  public static function getCallSiteMethodName() {
    $traceEntry = last(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4));
    return isset($traceEntry['class']) ?
      last(explode('\\', $traceEntry['class'])) . "::" . $traceEntry['function'] :
      $traceEntry['function'];
  }

  /**
   * Ensure context object is an array, performing conversion if necessary.
   * Optionally applies a prefix to the array keys.
   *
   * @param array|ILogContextData|Exception $contextObj
   * @return null
   */
  private static function contextObjectToArray($contextObj) {
    $data = null;

    if (is_array($contextObj)) {
      $data = $contextObj;
    }

    else if ($contextObj instanceof ILogContextData) {
      $data = $contextObj->getLogContextData();
    }

    else {
      if ($contextObj instanceof Exception) {
        $data = self::contextDataFromException($contextObj);
      }
    }

    if ($data === null) {
      _warn('invalid log context', ['ctx_obj' => json_encode($contextObj)]);
    }

    return $data;
  }

  /**
   * Make call to Laravel's Log Facade, automatically passing global
   * log context.
   *
   * @param string $level the log level ('info', 'warning', 'error')
   * @param $msg
   * @param mixed $appendedContextObj - key value array, or Exception
   * @param string|null $prefix - optional prefix applied to appended
   *  context entries derived from the appended context; eg,
   *
   *    _info('hello', ['bar1' => 1, 'bar_2' => 2], 'foo')
   *
   *  ...results in the following entries appended to the log
   *  context in the resulting log statement
   *
   *    [..., 'foo_bar1' => 1, 'foo_bar2' => 2]
   */
  public static function emitEntry(
    string $level,
    string $msg,
    $appendedContextObj = null,
    string $prefix = null
  ) {
    $appendedContext = static::contextObjectToArray($appendedContextObj);
    $ctx = static::formatContextData(
      LogContext::getContext($appendedContext),
      $prefix
    );
    $appendedContext['m'] = LogContext::getCallSiteMethodName();
    switch ($level) {
      case 'info':
        Log::info($msg, $ctx); break;
      case 'warning':
        Log::warning($msg, $ctx); break;
      case 'error':
        Log::error($msg, $ctx); break;
      default:
        Log::warning('invalid log level', $ctx);
    }
  }

  /**
   * Formats a context data key-value array for rendering in log event entry:
   * prefixes each context key; ensures each key value is a readable string;
   * dumps long string values to disk and substitutes with dump code (for
   * stack traces, raw post data, etc).
   *
   * @param $data
   * @param string|null $prefix
   * @return array
   */
  private static function formatContextData($data, $prefix = null): array {
    $formattedData = [];

    foreach ($data as $key => $value) {

      // Values must be encoded as string; we format objects and
      // arrays as json.
      $formattedValue = is_array($value) || is_object($value) ?
        json_encode($value) : (string)$value;

      // Large values will be dumped to disk and referenced in the log
      // statement with a dump id.
      $threshold = config('context-logger.dump-threshold', DEFAULT_DUMP_THRESHOLD);
      if (strlen($formattedValue) > $threshold) {
        $formattedValue = static::dumpValueToFile($formattedValue);
      }
      $formattedData[($prefix ? $prefix . '_' : '') . $key] = $formattedValue;
    }

    return $formattedData;
  }

}