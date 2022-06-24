<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Service;
use App\Models\Scheduler;
use Illuminate\Http\Request;
use App\Http\Requests\MyScheduleRequest;
use App\Business\StaffAvailabilityChecker;

class MyScheduleController extends Controller
{
    public function index()
    {
        $date = Carbon::parse(request()->input('date'));

        $dayScheduler = Scheduler::where('client_user_id', auth()->id())
            ->whereDate('from', $date->format('Y-m-d'))
            ->orderBy('from', 'ASC')
            ->get();

        return view('my-schedule.index')
            ->with([
                'date' => $date,
                'dayScheduler' => $dayScheduler,
            ]);
    }

    public function create()
    {
        $services = Service::all();
        $staffUsers = User::role('staff')->get();

        return view('my-schedule.create')->with([
            'services' => $services,
            'staffUsers' => $staffUsers,
        ]);
    }

    public function store(MyScheduleRequest $request)
    {
        $service = Service::find(request('service_id'));
        $from = Carbon::parse(request('from.date') . ' ' . request('from.time'));
        $to = Carbon::parse($from)->addMinutes($service->duration);
        $staffUser = User::find($request->input('staff_user_id'));

        if(! (new StaffAvailabilityChecker($staffUser, $from, $to))
            ->check()) {
            abort(back()->withErrors('Este horario no está disponible.')->withInput());
        }

        Scheduler::create([
            'from' => $from,
            'to' => $to,
            'status' => 'pending',
            'staff_user_id' => request('staff_user_id'),
            'client_user_id' => auth()->id(),
            'service_id' => $service->id,
        ]);

        return redirect(route('my-schedule', ['date' => $from->format('Y-m-d')]));
    }
}
