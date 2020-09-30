<?php

use App\Logging\LogContext;
use App\Logging\ILogContextData;
use function Sentry\captureException;
use function Sentry\captureMessage;


// ---------- SENTRY ---------- //

function _sentry($object) {

  if ($object instanceof Exception) {
    captureException($object);
    return;
  }

  if (is_string($object)) {
    captureMessage($object);
    return;
  }
}


// ---------- LOG EVENTS ---------- //

function _info($msg, $appendedContext = [], $prefix = null) {
  LogContext::emitEntry('info', $msg, $appendedContext, $prefix);
}

function _warn($msg, $appendedContext = [], $prefix = null) {
  LogContext::emitEntry('warning', $msg, $appendedContext, $prefix);
}

function _error($msg, $appendedContext = [], $prefix = null) {
  LogContext::emitEntry('error', $msg, $appendedContext, $prefix);
}


// ---------- LOG CONTEXT MODIFIERS ---------- //

/**
 * Push subcontext data onto the global log context stack,
 * which will be automatically included with the above log
 * event calls.
 *
 * @param string $subContextName
 * @param array|ILogContextData|Exception $contextObj
 */
function _push_log_ctx($subContextName, $contextObj) {
  LogContext::pushSubContext($subContextName, $contextObj);
}

/**
 * Remove a log subcontext by name.
 *
 * @param $subContextName
 */
function _pop_log_ctx($subContextName) {
  LogContext::popSubContext($subContextName);
}




