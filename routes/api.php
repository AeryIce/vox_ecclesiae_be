<?php

use App\Services\VoxPrepackGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'service' => 'vox-ecclesiae-be',
        'time' => now()->toIso8601String(),
    ]);
});

Route::post('/prepack', function (Request $request, VoxPrepackGenerator $generator) {
    $validated = $request->validate([
        'topic' => ['required', 'string', 'min:3', 'max:200'],
        'purpose' => ['required', 'string', 'min:3', 'max:80'],
        'audience' => ['required', 'string', 'min:2', 'max:50'],
        'duration_minutes' => ['required', 'integer', 'min:5', 'max:180'],
        'format' => ['required', 'string', 'in:interview,panel'],

        'guest_role_context' => ['required', 'string', 'min:3', 'max:200'],
        'must_points' => ['required', 'array', 'min:1', 'max:5'],
        'must_points.*' => ['required', 'string', 'min:2', 'max:140'],

        'salutation' => ['required', 'string', 'min:2', 'max:30'],
        'formality' => ['required', 'string', 'in:formal_ringan,sangat_formal,hangat_ramah'],

        'sensitive_constraints' => ['sometimes', 'nullable', 'string', 'max:300'],
    ]);

    return response()->json($generator->generate($validated));
})->middleware('throttle:20,1');