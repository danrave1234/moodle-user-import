<?php

declare(strict_types=1);

use App\ApplicationServices;
use App\Http\ApiErrorHandler;
use App\Http\ImportResponseMapper;
use App\Http\ImportUsersAction;
use App\Http\JsonResponder;
use App\Http\PreviewImportAction;
use App\Http\UploadedCsvValidator;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$services = static fn (): ApplicationServices => require dirname(__DIR__) . '/bootstrap.php';

$app = AppFactory::create();
$responder = new JsonResponder();
$uploadValidator = new UploadedCsvValidator();
$mapper = new ImportResponseMapper();

$app->post('/api/imports/preview', new PreviewImportAction(
    $services,
    $uploadValidator,
    $mapper,
    $responder,
));
$app->post('/api/imports', new ImportUsersAction(
    $services,
    $uploadValidator,
    $mapper,
    $responder,
));

$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(false, true, true);
$errorMiddleware->setDefaultErrorHandler(new ApiErrorHandler(new ResponseFactory(), $responder));

$app->run();
