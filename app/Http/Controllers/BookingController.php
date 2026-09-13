<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListBookingsRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Service;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function index(ListBookingsRequest $request, Service $service): AnonymousResourceCollection
    {
        $bookings = $service->bookings()
            ->when($request->from(), fn ($query, $from) => $query->where('ends_at', '>', $from))
            ->when($request->to(), fn ($query, $to) => $query->where('starts_at', '<', $to))
            ->orderBy('starts_at')
            ->get();

        return BookingResource::collection($bookings);
    }

    public function store(StoreBookingRequest $request, Service $service, BookingService $bookings): JsonResponse
    {
        $booking = $bookings->create($service, $request->validated());

        return (new BookingResource($booking))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Booking $booking, BookingService $bookings): BookingResource
    {
        return new BookingResource($bookings->cancel($booking));
    }
}
