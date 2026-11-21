<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    // GET /api/guests - view all guests
    public function index()
    {
        return response()->json(Guest::all());
    }

    // POST /api/guests - register a guest
    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:100',
            'email'     => 'required|email|max:100|unique:guests,email',
            'phone'     => 'nullable|string|max:20',
        ]);

        $guest = Guest::create($data);

        return response()->json(['message' => 'Guest created', 'guest' => $guest->fresh()], 201);
    }

    // PUT /api/guests/{id} - update a guest
    public function update(Request $request, $id)
    {
        $guest = Guest::find($id);
        if (!$guest) {
            return response()->json(['error' => 'Guest not found'], 404);
        }

        $data = $request->validate([
            'full_name' => 'sometimes|string|max:100',
            'email'     => 'sometimes|email|max:100|unique:guests,email,' . $id . ',guest_id',
            'phone'     => 'nullable|string|max:20',
        ]);

        $guest->update($data);

        return response()->json(['message' => 'Guest updated', 'guest' => $guest->fresh()]);
    }

    // DELETE /api/guests/{id} - delete a guest
    public function destroy($id)
    {
        $guest = Guest::find($id);
        if (!$guest) {
            return response()->json(['error' => 'Guest not found'], 404);
        }

        if ($guest->reservations()->exists()) {
            return response()->json(['error' => 'Cannot delete a guest that has reservations'], 409);
        }

        $guest->delete();

        return response()->json(['message' => 'Guest deleted']);
    }
}
