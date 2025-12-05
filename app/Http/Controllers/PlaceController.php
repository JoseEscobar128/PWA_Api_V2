<?php

namespace App\Http\Controllers;

use App\Models\Place;
use Illuminate\Http\Request;
use App\Http\Controllers\Traits\ApiResponse;

class PlaceController extends Controller
{
    use ApiResponse;

    /**
     * List all places with relations.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $places = Place::with(['photos', 'votes'])->get();
            return $this->success($places);
        } catch (\Exception $e) {
            return $this->error('Failed to list places', $e->getMessage(), 500);
        }
    }

    /**
     * Create a new place.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'address' => 'nullable|string|max:255',
                'lat' => 'required|numeric',
                'lng' => 'required|numeric',
                'category' => 'nullable|string|max:100',
            ]);

            $data['user_id'] = $request->user()->id;

            $place = Place::create($data);

            return $this->success($place, 'Place created', 201);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return $this->error('Validation failed', $ve->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Failed to create place', $e->getMessage(), 500);
        }
    }

    /**
     * Show a place with relations.
     *
     * @param Place $place
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Place $place)
    {
        try {
            $place->load(['photos', 'votes', 'reviews.user']);
            return $this->success($place);
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve place', $e->getMessage(), 500);
        }
    }

    /**
     * Update a place. Uses policy for authorization.
     *
     * @param Request $request
     * @param Place $place
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Place $place)
    {
        try {
            $this->authorize('update', $place);

            $data = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'address' => 'nullable|string|max:255',
                'lat' => 'sometimes|required|numeric',
                'lng' => 'sometimes|required|numeric',
                'category' => 'nullable|string|max:100',
            ]);

            $place->update($data);

            return $this->success($place, 'Place updated');
        } catch (\Illuminate\Auth\Access\AuthorizationException $ae) {
            return $this->error('Forbidden', null, 403);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return $this->error('Validation failed', $ve->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Failed to update place', $e->getMessage(), 500);
        }
    }

    /**
     * Delete a place (soft delete). Uses policy for authorization.
     *
     * @param Place $place
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Place $place)
    {
        try {
            $this->authorize('delete', $place);
            $place->delete();
            return $this->success(null, 'Place deleted');
        } catch (\Illuminate\Auth\Access\AuthorizationException $ae) {
            return $this->error('Forbidden', null, 403);
        } catch (\Exception $e) {
            return $this->error('Failed to delete place', $e->getMessage(), 500);
        }
    }
}
