<?php

include "vendor/autoload.php";
include "Interval.php";

class Service
{
    public array $schedules = [];

    public function __construct(
        public int $id,
        public int $workers = 1,
        public int $meeting_duration_minutes = 15
    )
    {
        /**
         * LUNI
         */
        $this->schedules[] = [
            'day_of_week' => 'L',
            "interval" => new Interval("10:00", "12:30")
        ];

        $this->schedules[] = [
            'day_of_week' => 'L',
            "interval" => new Interval("13:00", "17:00")
        ];
        /**
         * MARTI
         */
        $this->schedules[] = [
            'day_of_week' => 'Ma',
            "interval" => new Interval("10:00", "12:30")
        ];

        $this->schedules[] = [
            'day_of_week' => 'Mi',
            "interval" => new Interval("10:00", "12:30")
        ];

        $this->schedules[] = [
            'day_of_week' => 'J',
            "interval" => new Interval("10:00", "12:30")
        ];

        $this->schedules[] = [
            'day_of_week' => 'V',
            "interval" => new Interval("10:00", "12:30")
        ];
    }

    public function getDayOfWeekSchedule(string $day_of_week)
    {
        return array_filter($this->schedules, fn($it) => $it['day_of_week'] == $day_of_week);
    }
}

class Room
{
    public function __construct(
        public int $id,
        public Interval $open_hours
    )
    {

    }
}

class Reservation
{
    public function __construct(
        public int $id,
        public int $room_id,
        public Interval $reservation_interval,
        public int $service_id
    )
    {

    }
}

/**
 *ALL THE ROOMS
 */
$rooms = collect([

    new Room(1, new Interval("08:00", "17:00")),

    new Room(2, new Interval("08:00", "17:00")),

    new Room(3, new Interval("08:00", "17:00")),

]);


/**
 * ALL SERVICES
 */

$service_1 = new Service(1, 2, 11);

$service_2 = new Service(2, 3, 5);

$service_3 = new Service(3, 3, 7);

$service_4 = new Service(4, 3, 9);

$services = [$service_1, $service_2, $service_3, $service_4];

/**
 * RESERVATIONS
 */
$reservations = collect([

    new Reservation(1, 1, new Interval("11:30", "11:45"), $service_1->id),


    new Reservation(2, 1, new Interval("11:45", "12:15"), $service_2->id),

    new Reservation(3, 2, new Interval("11:45", "12:00"), $service_1->id),

//    new Reservation(3, 2, new Interval("11:45", "12:15"), $service_2->id),

    new Reservation(4, 1, new Interval("14:00", "14:30"), $service_2->id)

]);

function remove_closed_intervals_from_open_intervals(array $opened_intervals, array $closed_intervals): array
{
    foreach ($closed_intervals as $occ_interval) {
        foreach ($opened_intervals as $open_index => $open_interval) {
            if ($open_interval->includes($occ_interval)) {
                $copy = $open_interval;
                $sliced_intervals = $copy->slice($occ_interval);
                array_splice($opened_intervals, $open_index, 1, $sliced_intervals);
            }
        }
    }
    return $opened_intervals;
}

function room_availabilities($rooms, $reservations): array
{
    $intervals = [];

    foreach ($rooms as $i => $room) {
        $room_open_intervals = [$room->open_hours];
        // GET CURRENT ROOM RESERVATION
        $room_reservations = collect($reservations)->where('room_id', $room->id);
        // GET THE INTERVALS FROM THE ROOM RESERVATIONS
        $room_occupied_intervals = $room_reservations->pluck('reservation_interval')->toArray();
        $result = remove_closed_intervals_from_open_intervals($room_open_intervals, $room_occupied_intervals);

        $intervals = [
            ...$intervals,
            ...collect($result)
                ->map(fn($it) => [$room->id, $it])
                ->toArray()
        ];
    }
    return $intervals;
}

$reservation_service = $service_1;
/**
 * GET THE AVAILABLE INTERVALS BASED ON THE SERVICE OPENING HOURS
 */
$available_intervals = collect(
    $reservation_service->getDayOfWeekSchedule('L')
)
    ->map(fn($it) => $it['interval'])
    ->toArray();
/**
 * We now know when all of our rooms can accept new reservations
 *
 *  [
 *      [
 *         room_id,
 *         interval
 *      ]
 * ]
 *
 */
$room_available_intervals = room_availabilities($rooms, $reservations);
/**
 * FROM ROOMS SUBTRACTS THE SERVICE OPENING HOURS
 */


$overlap_results = [];

foreach ($available_intervals as $r1_interval) {
    foreach ($room_available_intervals as $room_interval) {
        if ($room_interval[1]->includes($r1_interval)) {
            // ???? | works
            $overlap_results[] = ['room_id' => $room_interval[0], 'interval' => $r1_interval];
        } else if ($r1_interval->overlaps($room_interval[1])) {
            $interval = $r1_interval->overlap($room_interval[1]);
            if ($interval) $overlap_results[] = ['room_id' => $room_interval[0], 'interval' => $interval];
        }
    }
}

/**
 * Each room within opening hours - reservations
 */

/**
 * BREAK THE INTERVALS INTO SLOTS FOR THIS SERVICE
 */


$available_slots = [];

/**
 * OVERLAP SHOULD BE SLICED
 */


foreach ($overlap_results as ['room_id' => $room_id, 'interval' => $interval]) {
    $total_slots_fit = collect($interval->segmentsOfMinutes($reservation_service->meeting_duration_minutes))
        ->map(fn($it) => ['room_id' => $room_id, 'interval' => $it]);
    $available_slots = [...$available_slots, ...$total_slots_fit];
}


// SEARCH FOR A RESERVATION AT A SPECIFIC TIME
//dd(
//    collect($available_slots)->filter(fn($it) => $it['interval']->start == '11:00')
//);


$reservations_service_occupied = $reservations->where('service_id', $reservation_service->id);


$service_start_occupied_positions = $reservations_service_occupied
    ->groupBy(fn($it) => $it->reservation_interval->start)
    ->map(fn($it) => $it->count());

//dd($service_start_occupied_positions);

$available_slots = collect($available_slots)->toArray();

dump("AVAILABLE BEFORE CHECKING WORKER AVAILABILITY", count($available_slots));

foreach ($service_start_occupied_positions as $time => $reservations_count) {
    if ($reservations_count >= $reservation_service->workers) {
        $available_slots = collect($available_slots)->filter(fn($it) => $it['interval']->start != $time)->toArray();
    }
}

dump("AVAILABLE AFTER CHECKING WORKER AVAILABILITY", $available_slots);

dd(collect($available_slots)->sortBy(fn($it) => $it['interval']->start_minutes)->toArray());