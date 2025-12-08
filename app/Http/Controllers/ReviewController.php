<?php
namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Place;
use Illuminate\Http\Request;
use App\Http\Controllers\Traits\ApiResponse;
use App\Notifications\NewReviewNotification;

class ReviewController extends Controller
{
    use ApiResponse;

    /**
     * List reviews with user and place.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $reviews = Review::with('user', 'place')->get();
            return $this->success($reviews);
        } catch (\Exception $e) {
            return $this->error('Failed to list reviews', $e->getMessage(), 500);
        }
    }

    /**
     * Store a new review.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'place_id' => 'required|exists:places,id',
                'rating'   => 'required|integer|min:1|max:5',
                'comment'  => 'nullable|string'
            ]);

            $data['user_id'] = $request->user()->id;

            $review = Review::create($data);
            $review->load('user');

            // Enviar notificación push al dueño del lugar
            $place = Place::with('user')->find($data['place_id']);
            if ($place && $place->user_id !== $request->user()->id) {
                $reviewerName = $request->user()->name . ' ' . $request->user()->last_name;
                $place->user->notify(new NewReviewNotification($review, $place, $reviewerName));
            }

            return $this->success($review, 'Review created', 201);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return $this->error('Validation failed', $ve->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Failed to create review', $e->getMessage(), 500);
        }
    }

    /**
     * Show a specific review.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $review = Review::with('user')->find($id);

            if (!$review) {
                return $this->error('Review not found', null, 404);
            }

            return $this->success($review);
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve review', $e->getMessage(), 500);
        }
    }

    /**
     * Update a review. Only owner or admin.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $review = Review::find($id);

            if (!$review) {
                return $this->error('Review not found', null, 404);
            }

            $user = $request->user();
            if ($review->user_id !== $user->id && !$user->hasRole('admin')) {
                return $this->error('Forbidden', null, 403);
            }

            $data = $request->validate([
                'rating'  => 'integer|min:1|max:5',
                'comment' => 'nullable|string'
            ]);

            $review->update($data);

            return $this->success($review, 'Review updated');
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return $this->error('Validation failed', $ve->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Failed to update review', $e->getMessage(), 500);
        }
    }

    /**
     * Delete a review. Only owner or admin.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        try {
            $review = Review::find($id);

            if (!$review) {
                return $this->error('Review not found', null, 404);
            }

            $user = $request->user();
            if ($review->user_id !== $user->id && !$user->hasRole('admin')) {
                return $this->error('Forbidden', null, 403);
            }

            $review->delete();

            return $this->success(null, 'Review deleted');
        } catch (\Exception $e) {
            return $this->error('Failed to delete review', $e->getMessage(), 500);
        }
    }
}
