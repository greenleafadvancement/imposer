<?php
namespace dirtsimple\imposer;

use GuzzleHttp\Promise as GP;
use GuzzleHttp\Promise\PromiseInterface;

class Promise {
	# Return watched promise for a value/promise or error
	static function value($val) { return WatchedPromise::wrap($val); }
	static function error($reason) { return new WatchedPromise( GP\Create::rejectionFor($reason) ); }

	# Synchronously return a value or throw an error; return default if pending
	static function now($val, $default=null) {
		if ( $val instanceof PromiseInterface ) {
			return ( $val->getState() === PromiseInterface::PENDING ) ? $default : $val->wait();
		}
		return $val;
	}

	# Interpret a yielded value from a generator or Task step
	static function interpret($data) {
		if ( \is_object($data) ) {
			if ( $data instanceof PromiseInterface )
				if ( $data->getState() === PromiseInterface::FULFILLED )
					return $data->wait();
				else return WatchedPromise::wrap($data);
			else if ( $data instanceof \Generator )
				return Promise::spawn($data);
			else if ( $data instanceof \Closure )
				return Promise::call($data);
			else return $data;
		} else if ( \is_array($data) ) {
			$all = false;
			foreach ($data as &$v) {
				$v = Promise::interpret($v);
				if ( $v instanceof PromiseInterface ) {
					$all = true;
					if ( $v->getState() === PromiseInterface::REJECTED )
						return $v;
				}
			}
			return $all ? new WatchedPromise( GP\Utils::all($data) ) : $data;
		} else return $data;
	}

	# call a function w/args and interpret the value, trapping errors as a promise
	static function call() {
		return (new WatchedPromise())->call(...func_get_args());
	}

	static function spawn($gen) {
		return (new WatchedPromise())->spawn($gen);
	}

	# Default handler for watched promises
	static function deferred_throw($reason) {
		GP\Utils::queue()->add(function() use ($reason) { throw GP\Create::exceptionFor($reason); });
	}

	# Guzzle wrappers and async utils
	static function sync() {
		GP\Utils::queue()->run();
	}

	static function later($cb, ...$args) {
		if ($args) $cb = function () use ($cb, $args) { $cb(...$args); };
		GP\Utils::queue()->add($cb);
	}

}