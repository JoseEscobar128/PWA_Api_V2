<?php

namespace App\Http\Controllers;

use App\Models\Place;
use App\Models\PlaceVote;
use Illuminate\Http\Request;
use App\Http\Controllers\Traits\ApiResponse;
use App\Notifications\NewVoteNotification;
use Illuminate\Support\Facades\Log;

class PlaceVoteController extends Controller
{
    use ApiResponse;

    /**
     * List votes for a place (optional `place_id`).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $placeId = $request->query('place_id');

            $votes = PlaceVote::when($placeId, function ($q, $placeId) {
                return $q->where('place_id', $placeId);
            })->get();

            return $this->success($votes);
        } catch (\Exception $e) {
            return $this->error('Failed to list votes', $e->getMessage(), 500);
        }
    }

    /**
     * Create a vote (one per user/place).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $placeId = $request->input('place_id');
            $userId = $request->user()->id;

            // Validate place exists
            if (!$placeId || !Place::where('id', $placeId)->exists()) {
                return $this->error('Validation failed', ['place_id' => ['The selected place does not exist.']], 422);
            }

            // Check if vote exists (including soft deleted ones)
            $existingVote = PlaceVote::withTrashed()
                ->where('place_id', $placeId)
                ->where('user_id', $userId)
                ->first();

            if ($existingVote) {
                // If it's soft deleted, restore it
                if ($existingVote->trashed()) {
                    $existingVote->restore();
                    $vote = $existingVote;
                } else {
                    // Vote already exists and is not deleted
                    $vote = $existingVote;
                }
            } else {
                // Create new vote
                $vote = PlaceVote::create([
                    'place_id' => $placeId,
                    'user_id' => $userId,
                ]);
            }

            // Enviar notificación push al dueño del lugar
            try {
                $place = Place::with('user')->find($placeId);
                if ($place && $place->user_id !== $userId) {
                    $voterName = $request->user()->name . ' ' . $request->user()->last_name;
                    $totalVotes = PlaceVote::where('place_id', $placeId)->count();
                    $place->user->notify(new NewVoteNotification($place, $voterName, $totalVotes));
                }
            } catch (\Exception $notificationError) {
                // Log notification error but don't fail the vote creation
                Log::warning('Failed to send vote notification: ' . $notificationError->getMessage());
            }

            // Return a simple response without relationships to avoid slow serialization
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $vote->id,
                    'place_id' => $vote->place_id,
                    'user_id' => $vote->user_id,
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Vote creation error: ' . $e->getMessage());
            return $this->error('Failed to vote', $e->getMessage(), 500);
        }
    }

    /**
     * Show whether the authenticated user has voted for the given place.
     *
     * @param int $place_id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($place_id, Request $request)
    {
        try {
            $vote = PlaceVote::where('place_id', $place_id)
                ->where('user_id', $request->user()->id)
                ->first();

            return $this->success([
                'voted' => (bool) $vote,
                'data' => $vote
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to check vote', $e->getMessage(), 500);
        }
    }

    /**
     * Remove authenticated user's vote for a place.
     *
     * @param int $place_id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($place_id, Request $request)
    {
        try {
            PlaceVote::where('place_id', $place_id)
                ->where('user_id', $request->user()->id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Vote removed'
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to remove vote', $e->getMessage(), 500);
        }
    }
}
