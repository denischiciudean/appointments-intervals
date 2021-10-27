<?php


use Carbon\Carbon;

class Interval
{

    public int $start_minutes;
    public int $end_minutes;

    private function carbon_to_minutes(Carbon $instance): int
    {
        $copy = $instance->copy();
        return $instance->startOfDay()->diffInMinutes($copy);
    }

    public function __construct(
        public string $start,
        public string $end,
        public string $format = 'H:i'
    )
    {
        $this->start_minutes = $this->carbon_to_minutes(Carbon::createFromFormat($this->format, $this->start));
        $this->end_minutes = $this->carbon_to_minutes(Carbon::createFromFormat($this->format, $this->end));
    }

    /**
     * @param int $start_minutes
     * @param int $end_minutes
     * @return Interval
     */
    public static function newByMinutes(
        int $start_minutes,
        int $end_minutes
    )
    {
        return new Interval(
            Carbon::now()->startOfDay()->addMinutes($start_minutes)->format('H:i'),
            Carbon::now()->startOfDay()->addMinutes($end_minutes)->format('H:i'),
            "H:i"
        );
    }

    /**
     * @param Interval $other
     * @return bool
     */
    public function isEqual(Interval $other): bool
    {
        return $this->start_minutes == $other->start_minutes && $this->end == $other->end_minutes;
    }

    /**
     * @return int
     */
    public function duration(): int
    {
        return $this->end_minutes - $this->start_minutes;
    }

    /**
     * @param Interval $other
     */
    public function includes(Interval $other)
    {
        return $this->start_minutes <= $other->start_minutes && $other->end_minutes <= $this->end_minutes;
    }

    /**
     * @param Interval $other
     */
    public function overlaps(Interval $other)
    {
        //this = 10:00-12:30
        //other = 08:00-11:30
        return (
            (
                //08:00 >= 10:00 | FALSE
                $other->start_minutes >= $this->start_minutes &&
                //08:00 <= 12:30 | TRUE
                $other->start_minutes <= $this->end_minutes
            ) // FALSE
            || // OR
            (
                //11:30 >= 10:00 | TRUE
                $other->end_minutes >= $this->start_minutes &&
                //11:30 <= 12:30 | TRUE
                $other->end_minutes <= $this->end_minutes
            ) // TRUE
        );
    }

    /**
     * @param Interval $other
     */
    public function overlap(Interval $other): Interval|array|null
    {
        if (
        !$this->overlaps($other)
            // this will return a null, meaning ommited
//            $this->start_minutes >= $other->end_minutes ||
//            $this->end_minutes >= $other->start_minutes
        ) {
            return null;
        }

        return self::newByMinutes(
            $this->start_minutes >= $other->start_minutes ? $this->start_minutes : $other->start_minutes,
            $this->end_minutes <= $other->end_minutes ? $this->end_minutes : $other->end_minutes
        );
    }

    /**
     * Slices this interval into 2 intervals by excluding the given interval
     *
     * @param Interval $other
     * @return array
     */
    public function slice(Interval $other): array
    {
        if (!$this->includes($other)) {
            return [];
        }
        if ($this->start == $other->start) {
            return [
                self::newByMinutes($other->end_minutes, $this->end_minutes),
            ];
        }
//        dump("-----------");
//        dump($this, $other);
//        dump("------000000000----- ");
        return [
            self::newByMinutes($this->start_minutes, $other->start_minutes),
            self::newByMinutes($other->end_minutes, $this->end_minutes),
        ];
    }

    /**
     * @param int $minutes
     * @return array
     */
    public function segmentsOfMinutes(int $minutes): array
    {
        $segments = [];
        $total_segments = (int)$this->duration() / $minutes;
        for ($i = 0; $i < $total_segments; $i++) {
            $start = $this->start_minutes + $i * $minutes;
            $end = $start + $minutes;
            if ($end > $this->end_minutes) {
                continue;
            }
            $segments[] = self::newByMinutes($start, $end);
        }
        return $segments;
    }
}