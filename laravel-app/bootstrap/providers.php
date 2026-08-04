<?php

use App\Providers\AiDatasetServiceProvider;
use App\Providers\AiExplanationServiceProvider;
use App\Providers\AiInferenceServiceProvider;
use App\Providers\AiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    AiServiceProvider::class,
    AiExplanationServiceProvider::class,
    AiInferenceServiceProvider::class,
    AiDatasetServiceProvider::class,
    FortifyServiceProvider::class,
];
