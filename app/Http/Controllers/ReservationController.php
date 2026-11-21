<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReservationController extends Controller
{
    // GET /api/reservations?guest_id=1&status=CONFIRMED
    public function index(Request $request)
    {
        $query = Reservation::query();

        if ($request->filled('guest_id')) {
            $query->where('guest_id', $request->guest_id);
        }
        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->status));
        }

        return response()->json($query->orderBy('check_in')->get());
    }

    // GET /api/reservations/{id}
    public function show($id)
    {
        $reservation = Reservation::find($id);

        if (!$reservation) {
            return response()->json(['error' => 'Reservation not found'], 404);
        }

        return response()->json($reservation);
    }

    // POST /api/reservations  (Transaction 1: create reservation)
    public function store(Request $request)
    {
        $data = $request->validate([
            'guest_id'  => 'required|integer|exists:guests,guest_id',
            'room_id'   => 'required|integer|exists:rooms,room_id',
            'check_in'  => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
        ]);

        try {
            $reservation = DB::transaction(function () use ($data) {
                // 1. Lock the room row; other bookings for this room must wait here
                $room = Room::where('room_id', $data['room_id'])->lockForUpdate()->first();

                // 2. Check overlapping confirmed bookings (safe now, because of the lock)
                if ($this->hasOverlap($room->room_id, $data['check_in'], $data['check_out'])) {
                    abort(409, 'Room already booked for these dates');
                }

                // 3. Calculate total and insert
                $nights = $this->nights($data['check_in'], $data['check_out']);

                $reservation = new Reservation();
                $reservation->guest_id     = $data['guest_id'];
                $reservation->room_id      = $room->room_id;
                $reservation->check_in     = $data['check_in'];
                $reservation->check_out    = $data['check_out'];
                $reservation->total_amount = $room->price_per_night * $nights;
                $reservation->status       = 'CONFIRMED';
                $reservation->save();

                return $reservation->refresh(); // load DB defaults (created_at)
            });
        } catch (HttpException $e) {
            // abort() inside the transaction triggers an automatic ROLLBACK
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Could not create reservation'], 500);
        }

        return response()->json([
            'message'     => 'Reservation created',
            'reservation' => $reservation,
        ], 201);
    }

    // PUT /api/reservations/{id}  (Transaction 2: change dates)
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'check_in'  => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
        ]);

        try {
            $reservation = DB::transaction(function () use ($id, $data) {
                $probe = Reservation::find($id);
                if (!$probe) {
                    abort(404, 'Reservation not found');
                }

                // Same lock order as store(): room first, then reservation
                $room = Room::where('room_id', $probe->room_id)->lockForUpdate()->first();
                $reservation = Reservation::where('reservation_id', $id)->lockForUpdate()->first();

                if ($reservation->status !== 'CONFIRMED') {
                    abort(409, 'Only CONFIRMED reservations can be updated');
                }

                // Ignore this same reservation in the overlap check
                if ($this->hasOverlap($room->room_id, $data['check_in'], $data['check_out'], $reservation->reservation_id)) {
                    abort(409, 'Room already booked for these dates');
                }

                $nights = $this->nights($data['check_in'], $data['check_out']);

                $reservation->check_in     = $data['check_in'];
                $reservation->check_out    = $data['check_out'];
                $reservation->total_amount = $room->price_per_night * $nights;
                $reservation->save();

                return $reservation->refresh(); // reload so total_amount is formatted as "36000.00"
            });
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Could not update reservation'], 500);
        }

        return response()->json([
            'message'     => 'Reservation updated',
            'reservation' => $reservation,
        ]);
    }

    // PATCH /api/reservations/{id}/cancel  (Transaction 3: cancel)
    public function cancel($id)
    {
        try {
            $reservation = DB::transaction(function () use ($id) {
                $probe = Reservation::find($id);
                if (!$probe) {
                    abort(404, 'Reservation not found');
                }

                // Same lock order: room first, then reservation
                Room::where('room_id', $probe->room_id)->lockForUpdate()->first();
                $reservation = Reservation::where('reservation_id', $id)->lockForUpdate()->first();

                if ($reservation->status === 'CANCELLED') {
                    abort(409, 'Reservation is already cancelled');
                }

                $reservation->status = 'CANCELLED';
                $reservation->save();

                return $reservation->refresh();
            });
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Could not cancel reservation'], 500);
        }

        return response()->json([
            'message'     => 'Reservation cancelled',
            'reservation' => $reservation,
        ]);
    }

    // Overlap rule: existing.check_in < new.check_out AND existing.check_out > new.check_in
    private function hasOverlap($roomId, $checkIn, $checkOut, $ignoreId = null): bool
    {
        return Reservation::where('room_id', $roomId)
            ->where('status', 'CONFIRMED')
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->when($ignoreId, fn ($q) => $q->where('reservation_id', '!=', $ignoreId))
            ->exists();
    }

    private function nights($checkIn, $checkOut): int
    {
        return (int) Carbon::parse($checkIn)->startOfDay()
            ->diffInDays(Carbon::parse($checkOut)->startOfDay());
    }
}
