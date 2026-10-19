<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OcrController extends Controller
{
    public function process(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        try {
            $response = Http::connectTimeout(5)
                ->timeout(60)
                ->attach(
                    'image',
                    $request->file('image')->get(),
                    $request->file('image')->getClientOriginalName()
                )
                ->post(config('services.ocr.url'), [
                    'longitude' => $validated['longitude'] ?? '',
                    'latitude' => $validated['latitude'] ?? '',
                    'username' => $request->user()->name,
                ])
                ->throw();
        } catch (ConnectionException $exception) {
            Log::warning('Unable to connect to the OCR service.', [
                'message' => $exception->getMessage(),
            ]);

            return back()->withErrors([
                'image' => 'The OCR service is unavailable. Please try again later.',
            ]);
        } catch (RequestException $exception) {
            Log::error('The OCR service rejected an image upload.', [
                'status' => $exception->response->status(),
            ]);

            return back()->withErrors([
                'image' => 'The OCR service could not process this image.',
            ]);
        }

        return redirect()->route('dashboard')->with('ocr_result', $response->body());
    }
}
