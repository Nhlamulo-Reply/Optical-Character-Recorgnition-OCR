<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">OCR image capture</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h1 class="text-lg font-semibold text-gray-900">Capture or upload an image</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Capture a photo with your webcam or choose an image file. Your signed-in account name is sent to the OCR service.
                </p>

                @if (session('status'))
                    <div role="status" class="mt-4 rounded-md bg-green-50 p-4 text-sm text-green-800">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div role="alert" class="mt-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session()->has('ocr_result'))
                    <div class="mt-4 rounded-md bg-blue-50 p-4">
                        <h2 class="font-medium text-blue-900">OCR service response</h2>
                        <pre class="mt-2 whitespace-pre-wrap break-words text-sm text-blue-950">{{ session('ocr_result') }}</pre>
                    </div>
                @endif
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                    <div>
                        <h2 class="font-semibold text-gray-900">Webcam</h2>
                        <p id="cameraStatus" class="mt-1 text-sm text-gray-600" role="status">Starting camera…</p>
                    </div>

                    <video id="video" class="aspect-video w-full rounded-md bg-gray-900 object-cover" autoplay playsinline></video>
                    <button id="capture" type="button" disabled
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                        Capture and send
                    </button>
                    <canvas id="canvas" width="1280" height="720" class="hidden"></canvas>
                </section>

                <section class="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                    <div>
                        <h2 class="font-semibold text-gray-900">Image and location</h2>
                        <p id="locationStatus" class="mt-1 text-sm text-gray-600" role="status">Requesting your location…</p>
                        <p id="address" class="mt-1 text-sm text-gray-600"></p>
                    </div>

                    <img id="capturedImage" class="hidden max-h-64 w-full rounded-md border border-gray-200 object-contain" alt="Selected image preview">

                    <form id="uploadForm" method="POST" action="{{ route('ocr.process') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div>
                            <label for="imageInput" class="block text-sm font-medium text-gray-700">Image</label>
                            <input id="imageInput" name="image" type="file" accept="image/*" required
                                class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm text-gray-700 file:mr-4 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-2">
                            <p class="mt-1 text-xs text-gray-500">Image files up to 10 MB.</p>
                        </div>

                        <input id="latitude" name="latitude" type="hidden" value="{{ old('latitude') }}">
                        <input id="longitude" name="longitude" type="hidden" value="{{ old('longitude') }}">

                        <button type="submit"
                            class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                            Send image for OCR
                        </button>
                    </form>

                    <p class="text-xs text-gray-500">
                        Location is attached when permission is granted. You can still upload an image without it.
                    </p>
                </section>
            </div>

            <div id="ocrSettings"
                data-geocoding-key="{{ config('services.google.maps_geocoding_key') }}"
                class="hidden"></div>
        </div>
    </div>

    <script>
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const captureButton = document.getElementById('capture');
        const uploadForm = document.getElementById('uploadForm');
        const imageInput = document.getElementById('imageInput');
        const capturedImage = document.getElementById('capturedImage');
        const cameraStatus = document.getElementById('cameraStatus');
        const locationStatus = document.getElementById('locationStatus');
        const addressDisplay = document.getElementById('address');
        const geocodingKey = document.getElementById('ocrSettings').dataset.geocodingKey;
        let cameraReady = false;
        let locationGranted = false;

        function updateCaptureButton() {
            captureButton.disabled = !(cameraReady && locationGranted);
        }

        if (navigator.mediaDevices?.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then((stream) => {
                    video.srcObject = stream;
                    cameraReady = true;
                    cameraStatus.textContent = 'Camera ready.';
                    updateCaptureButton();
                })
                .catch(() => {
                    cameraStatus.textContent = 'Camera access is unavailable. You can still choose an image file.';
                });
        } else {
            cameraStatus.textContent = 'This browser does not support webcam capture. You can still choose an image file.';
        }

        if ('geolocation' in navigator) {
            navigator.geolocation.getCurrentPosition(
                ({ coords }) => {
                    const { latitude, longitude } = coords;
                    document.getElementById('latitude').value = latitude;
                    document.getElementById('longitude').value = longitude;
                    locationGranted = true;
                    locationStatus.textContent = `Location: ${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;
                    updateCaptureButton();

                    if (geocodingKey) {
                        const query = new URLSearchParams({
                            latlng: `${latitude},${longitude}`,
                            key: geocodingKey,
                        });

                        fetch(`https://maps.googleapis.com/maps/api/geocode/json?${query}`)
                            .then((response) => {
                                if (!response.ok) {
                                    throw new Error('Address lookup failed.');
                                }
                                return response.json();
                            })
                            .then((data) => {
                                addressDisplay.textContent = data.results?.[0]?.formatted_address
                                    ? `Approximate address: ${data.results[0].formatted_address}`
                                    : 'Address not found.';
                            })
                            .catch(() => {
                                addressDisplay.textContent = 'Unable to look up the address.';
                            });
                    }
                },
                () => {
                    locationStatus.textContent = 'Location was not shared. Image uploads are still available.';
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        } else {
            locationStatus.textContent = 'Geolocation is not supported. Image uploads are still available.';
        }

        imageInput.addEventListener('change', () => {
            const [file] = imageInput.files;
            if (!file) {
                capturedImage.classList.add('hidden');
                return;
            }

            capturedImage.src = URL.createObjectURL(file);
            capturedImage.classList.remove('hidden');
        });

        captureButton.addEventListener('click', () => {
            const context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            canvas.toBlob((blob) => {
                if (!blob) {
                    cameraStatus.textContent = 'The image could not be captured. Please try again.';
                    return;
                }

                const file = new File([blob], 'captured-image.jpg', { type: 'image/jpeg' });
                const transfer = new DataTransfer();
                transfer.items.add(file);
                imageInput.files = transfer.files;
                capturedImage.src = URL.createObjectURL(file);
                capturedImage.classList.remove('hidden');
                uploadForm.requestSubmit();
            }, 'image/jpeg', 0.9);
        });

        window.addEventListener('pagehide', () => {
            video.srcObject?.getTracks().forEach((track) => track.stop());
        });
    </script>
</x-app-layout>
