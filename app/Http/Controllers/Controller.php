<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(title="Drawly API", version="1.0.0")
 * @OA\SecurityScheme(
 *     securityScheme="X-DEVICE-ID",
 *     type="apiKey",
 *     in="header",
 *     name="X-DEVICE-ID"
 * )
 */
abstract class Controller
{
}
