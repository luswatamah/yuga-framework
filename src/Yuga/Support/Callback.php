<?php

namespace Yuga\Support;

use function is_array, is_object, is_string;
use Yuga\Exceptions\InvalidArgumentException;


/**
 * PHP callable tools.
 */
final class Callback
{

	/**
	 * @param  string|object|callable  $callable  class, object, callable
	 * @deprecated use Closure::fromCallable()
	 */
	public static function closure($callable, string $method = null): \Closure
	{
		try {
			return \Closure::fromCallable($method === null ? $callable : [$callable, $method]);
		} catch (\TypeError $e) {
			throw new \InvalidArgumentException($e->getMessage());
		}
	}


	/**
	 * Invokes callback.
	 * @return mixed
	 * @deprecated
	 */
	public static function invoke($callable, ...$args)
	{
		trigger_error(__METHOD__ . '() is deprecated, use native invoking.', E_USER_DEPRECATED);
		self::check($callable);
		return $callable(...$args);
	}


	/**
	 * Invokes callback with an array of parameters.
	 * @return mixed
	 * @deprecated
	 */
	public static function invokeArgs($callable, array $args = [])
	{
		trigger_error(__METHOD__ . '() is deprecated, use native invoking.', E_USER_DEPRECATED);
		self::check($callable);
		return $callable(...$args);
	}


	/**
	 * Invokes internal PHP function with own error handler.
	 * @return mixed
	 */
	public static function invokeSafe(string $function, array $args, callable $onError)
	{
		$prev = set_error_handler(function ($severity, $message, $file) use ($onError, &$prev, $function): ?bool {
			if ($file === __FILE__) {
				$msg = $message;
				if (ini_get('html_errors')) {
					$msg = html_entity_decode(strip_tags($msg));
				}
				$msg = preg_replace("#^$function\(.*?\): #", '', $msg);
				if ($onError($msg, $severity) !== false) {
					return null;
				}
			}
			return $prev ? $prev(...func_get_args()) : false;
		});

		try {
			return $function(...$args);
		} finally {
			restore_error_handler();
		}
	}


	/**
	 * @return callable
	 */
	public static function check($callable, bool $syntax = false)
	{
		if (!is_callable($callable, $syntax)) {
			throw new InvalidArgumentException($syntax
				? 'Given value is not a callable type.'
				: sprintf("Callback '%s' is not callable.", self::toString($callable))
			);
		}
		return $callable;
	}


	public static function toString($callable): string
	{
		if ($callable instanceof \Closure) {
			$inner = self::unwrap($callable);
			return '{closure' . ($inner instanceof \Closure ? '}' : ' ' . self::toString($inner) . '}');
		} elseif (is_string($callable) && $callable[0] === "\0") {
			return '{lambda}';
		} else {
			is_callable(is_object($callable) ? [$callable, '__invoke'] : $callable, true, $textual);
			return $textual;
		}
	}


	public static function toReflection($callable): \ReflectionFunctionAbstract
	{
		if ($callable instanceof \Closure) {
			$callable = self::unwrap($callable);
		}

		if (is_string($callable) && strpos($callable, '::')) {
			return new \ReflectionMethod($callable);
		} elseif (is_array($callable)) {
			return new \ReflectionMethod($callable[0], $callable[1]);
		} elseif (is_object($callable) && !$callable instanceof \Closure) {
			return new \ReflectionMethod($callable, '__invoke');
		} else {
			return new \ReflectionFunction($callable);
		}
	}


	public static function isStatic(callable $callable): bool
	{
		return is_array($callable) ? is_string($callable[0]) : is_string($callable);
	}


	/**
	 * Unwraps closure created by Closure::fromCallable()
	 * @internal
	 */
	public static function unwrap(\Closure $closure): callable
	{
		$r = new \ReflectionFunction($closure);
		if (substr($r->getName(), -1) === '}') {
			return $closure;

		} elseif ($obj = $r->getClosureThis()) {
			return [$obj, $r->getName()];

		} elseif ($class = $r->getClosureScopeClass()) {
			return [$class->getName(), $r->getName()];

		} else {
			return $r->getName();
		}
	}
}
