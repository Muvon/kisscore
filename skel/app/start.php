<?php declare(strict_types=1);

if (isset($_SERVER['REQUEST_METHOD'])) {
	App::setExceptionHandler(
		Throwable::class, function (Throwable $T) {
			App::logException($T);
			$response = [
				$T instanceof ResultError ? $T->getMessage() : 'e_error',
				App::$debug ? $T->getTraceAsString() : null,
			];
			return Response::current()
				->status(400)
				->header('Content-type', 'application/json; charset=utf8')
				->send((string)json_encode($response));
		}
	);
}
