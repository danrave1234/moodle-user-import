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

/** @var ApplicationServices $services */
$services = require dirname(__DIR__) . '/bootstrap.php';

$app = AppFactory::create();
$responder = new JsonResponder();
$uploadValidator = new UploadedCsvValidator();
$mapper = new ImportResponseMapper();

$app->post('/api/imports/preview', new PreviewImportAction(
    $services->userImportService,
    $uploadValidator,
    $mapper,
    $responder,
));
$app->post('/api/imports', new ImportUsersAction(
    $services->userImportService,
    $uploadValidator,
    $mapper,
    $responder,
));

$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(false, true, true);
$errorMiddleware->setDefaultErrorHandler(new ApiErrorHandler(new ResponseFactory(), $responder));

$app->run();
