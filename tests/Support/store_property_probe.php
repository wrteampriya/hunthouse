<?php

/**
 * Subprocess probe: exercise PropertController@store end-to-end (DB write).
 *
 * store() answers success via ResponseService::successResponse(), which calls
 * exit() (codebase-wide convention), so it cannot run inside the PHPUnit
 * process. This probe runs it in a child process instead:
 *   - exit 0 + {"error":false,...} on success (output is the JSON response)
 *   - exit 2 + validation errors JSON when validation rejects the input
 *   - exit 1 + message on any other failure
 *
 * Usage:
 *   php tests/Support/store_property_probe.php --admin=<userId> [--seller=<customerId>] [--title="..."]
 */

use App\Http\Controllers\PropertController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$options = getopt('', ['admin:', 'seller::', 'title::']);

$adminId = (int) ($options['admin'] ?? 0);
if ($adminId <= 0 || ! Auth::loginUsingId($adminId)) {
    echo json_encode(['error' => true, 'message' => 'Probe: admin login failed']);
    exit(1);
}

$params = [
    'is_draft' => '1',
    'title' => $options['title'] ?? ('Probe Property '.uniqid()),
    'category' => '',
    'address' => 'Probe Address',
    'price' => '',
];
if (! empty($options['seller'])) {
    $params['seller_id'] = $options['seller'];
}

try {
    $request = Request::create('/property', 'POST', $params);
    // Match the web stack closely enough for validation/translation code.
    $request->setLaravelSession($app['session']->driver());
    (new PropertController)->store($request);

    // store() always responds (success) or throws — reaching here is a bug.
    echo json_encode(['error' => true, 'message' => 'Probe: store returned without responding']);
    exit(1);
} catch (ValidationException $e) {
    echo json_encode(['error' => true, 'message' => 'Validation failed', 'errors' => $e->errors()]);
    exit(2);
} catch (Throwable $e) {
    echo json_encode(['error' => true, 'message' => 'Probe exception: '.$e->getMessage()]);
    exit(1);
}
