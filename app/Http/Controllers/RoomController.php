<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    // GET /api/rooms - view all rooms
    public function index()
    {
        return response()->json(Room::all());
    }

    // POST /api/rooms - add a room
    public function store(Request $request)
    {
        $data = $request->validate([
            'room_number'     => 'required|string|max:10|unique:rooms,room_number',
            'room_type'       => 'required|string|max:30',
            'price_per_night' => 'required|numeric|gt:0',
            'branch'          => 'required|string|max:50',
        ]);

        $room = Room::create($data);

        return response()->json([
            'message' => 'Room created',
            'room'    => $room->fresh(),
        ], 201);
    }

    // PUT /api/rooms/{id} - update a room
    public function update(Request $request, $id)
    {
        $room = Room::find($id);
        if (!$room) {
            return response()->json(['error' => 'Room not found'], 404);
        }

        $data = $request->validate([
            'room_number'     => 'sometimes|string|max:10|unique:rooms,room_number,' . $id . ',room_id',
            'room_type'       => 'sometimes|string|max:30',
            'price_per_night' => 'sometimes|numeric|gt:0',
            'branch'          => 'sometimes|string|max:50',
            'status'          => 'sometimes|string|max:20',
        ]);

        $room->update($data);

        return response()->json(['message' => 'Room updated', 'room' => $room->fresh()]);
    }

    // DELETE /api/rooms/{id} - delete a room
    public function destroy($id)
    {
        $room = Room::find($id);
        if (!$room) {
            return response()->json(['error' => 'Room not found'], 404);
        }

        // Rooms with reservations cannot be deleted (foreign key)
        if ($room->reservations()->exists()) {
            return response()->json(['error' => 'Cannot delete a room that has reservations'], 409);
        }

        $room->delete();

        return response()->json(['message' => 'Room deleted']);
    }

    // GET /api/rooms/available?check_in=...&check_out=... - available rooms
    public function available(Request $request)
    {
        $request->validate([
            'check_in'  => 'required|date',
            'check_out' => 'required|date|after:check_in',
        ]);

        // A room is free if it has no confirmed reservation overlapping the dates
        $rooms = Room::whereNotIn('room_id', function ($q) use ($request) {
            $q->select('room_id')
                ->from('reservations')
                ->where('status', 'CONFIRMED')
                ->where('check_in', '<', $request->check_out)
                ->where('check_out', '>', $request->check_in);
        })->get();

        return response()->json($rooms);
    }
}
