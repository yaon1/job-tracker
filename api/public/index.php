<?php

declare(strict_types=1);

use JobTracker\App;
use JobTracker\Database;
use JobTracker\HhImporter;
use JobTracker\Http\Request;
use JobTracker\Http\Response;

require dirname(__DIR__) . '/vendor/autoload.php';

$origin = getenv('CORS_ORIGIN') ?: 'http://localhost:3000';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$request = Request::fromGlobals();
if ($request->method === 'OPTIONS') {
    Response::noContent()->send();

    return;
}

$pdo = Database::connect('sqlite:' . (getenv('DATABASE_PATH') ?: dirname(__DIR__) . '/data.sqlite'));
App::routes($pdo, new HhImporter(HhImporter::httpFetcher()))->dispatch($request)->send();
