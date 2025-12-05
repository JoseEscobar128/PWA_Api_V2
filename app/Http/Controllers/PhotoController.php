<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Traits\ApiResponse;

class PhotoController extends Controller
{
    use ApiResponse;

    /**
     * List photos. Optionally filter by `place_id`.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            if ($request->has('place_id')) {
                $photos = Photo::where('place_id', $request->place_id)->get();
            } else {
                $photos = Photo::all();
            }

            return $this->success($photos);
        } catch (\Exception $e) {
            return $this->error('Failed to list photos', $e->getMessage(), 500);
        }
    }

    /**
     * Store a new photo for a place.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'place_id' => 'required|integer',
                'photo' => 'required|image|max:10240'
            ]);

            // Verificar que el lugar existe sin eager load
            if (!$placeExists = DB::table('places')->where('id', $data['place_id'])->exists()) {
                return $this->error('Validation failed', ['place_id' => ['The selected place does not exist.']], 422);
            }

            $path = $request->file('photo')->store('places', 'public');

            $photo = Photo::create([
                'place_id' => $data['place_id'],
                'user_id' => $request->user()->id,
                'url' => $path
            ]);

            return $this->success($photo, 'Photo uploaded', 201);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return $this->error('Validation failed', $ve->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Failed to upload photo', $e->getMessage(), 500);
        }
    }

    /**
     * Show a photo.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $photo = Photo::find($id);

            if (!$photo) {
                return $this->error('Photo not found', null, 404);
            }

            return $this->success($photo);
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve photo', $e->getMessage(), 500);
        }
    }

    /**
     * Update a photo (replace file). Only owner or admins can update.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $photo = Photo::find($id);

            if (!$photo) {
                return $this->error('Photo not found', null, 404);
            }

            $user = $request->user();
            if ($photo->user_id !== $user->id && !method_exists($user, 'hasRole')) {
                // if there's no role system yet, disallow
                return $this->error('Forbidden', null, 403);
            }

            if ($photo->user_id !== $user->id && !$user->hasRole('admin')) {
                return $this->error('Forbidden', null, 403);
            }

            $data = $request->validate([
                'photo' => 'required|image|max:10240',
                'description' => 'nullable|string'
            ]);

            if (Storage::disk('public')->exists($photo->url)) {
                Storage::disk('public')->delete($photo->url);
            }

            $path = $request->file('photo')->store('places', 'public');

            $updateData = ['url' => $path];
            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }

            $photo->update($updateData);

            return $this->success($photo, 'Photo updated');
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return $this->error('Validation failed', $ve->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Failed to update photo', $e->getMessage(), 500);
        }
    }

    /**
     * Delete a photo (soft delete + remove file). Only owner or admin.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $photo = Photo::find($id);

            if (!$photo) {
                return $this->error('Photo not found', null, 404);
            }

            $user = request()->user();
            if ($photo->user_id !== $user->id && !$user->hasRole('admin')) {
                return $this->error('Forbidden', null, 403);
            }

            if (Storage::disk('public')->exists($photo->url)) {
                Storage::disk('public')->delete($photo->url);
            }

            $photo->delete();

            return $this->success(null, 'Photo deleted');
        } catch (\Exception $e) {
            return $this->error('Failed to delete photo', $e->getMessage(), 500);
        }
    }
}
